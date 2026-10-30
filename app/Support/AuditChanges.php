<?php

namespace App\Support;

use App\Admin\ResourceRegistry;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * An audit row read by a person: each change as field, before and after,
 * and the record it is about, linked while it still exists.
 *
 * The rows were written over two years by several hands, so the changes
 * column comes in more than one shape: {field: {from, to}} from the model
 * events, {attributes: {...}} on create and delete, {from, to} for a role
 * change, a list of translated fields, or a single flag such as restored.
 * Each is read into the same three columns.
 *
 * Secrets are masked here as well as at the source. The trait redacts them
 * on write; a row written by hand, or before a column was named, is caught
 * on read by the same pattern.
 */
final class AuditChanges
{
    /** Values longer than this are shortened, with the full text one click away. */
    public const SHORT = 80;

    private const SECRET = '/password|token|secret|recovery|two_factor|2fa/i';

    private const QUIET = ['id', 'created_at', 'updated_at', 'last_seen_at'];

    /**
     * @return list<array{field: string, before: ?string, after: ?string, masked: bool}>
     */
    public static function rows(AuditLog $entry): array
    {
        $changes = $entry->changes;

        if (! is_array($changes) || $changes === []) {
            return [];
        }

        // A role change: {from, to} at the top.
        if (array_keys($changes) === ['from', 'to'] || array_keys($changes) === ['to', 'from']) {
            return [self::row('role', $changes['from'], $changes['to'])];
        }

        $rows = [];

        foreach ($changes as $key => $value) {
            $key = (string) $key;

            if ($key === 'attributes' && is_array($value)) {
                foreach ($value as $attribute => $attributeValue) {
                    if (in_array($attribute, self::QUIET, true)) {
                        continue;
                    }
                    $rows[] = $entry->action === AuditLog::DELETED
                        ? self::row((string) $attribute, $attributeValue, null)
                        : self::row((string) $attribute, null, $attributeValue);
                }

                continue;
            }

            if (is_array($value) && array_key_exists('from', $value) && array_key_exists('to', $value)) {
                $rows[] = self::row($key, $value['from'], $value['to']);

                continue;
            }

            // An older translation row names the fields that moved.
            if ($key === 'translations' && is_array($value) && array_is_list($value)) {
                $rows[] = [
                    'field' => self::label('translations'),
                    'before' => null,
                    'after' => implode(', ', array_map(fn ($f) => self::label((string) $f), $value)),
                    'masked' => false,
                ];

                continue;
            }

            $rows[] = self::row($key, null, $value);
        }

        return $rows;
    }

    /** @return array{field: string, before: ?string, after: ?string, masked: bool} */
    private static function row(string $key, mixed $before, mixed $after): array
    {
        $masked = self::isSecret($key) || $before === '[redacted]' || $after === '[redacted]';

        return [
            'field' => self::label($key),
            'before' => $masked ? null : self::text($before),
            'after' => $masked ? null : self::text($after),
            'masked' => $masked,
        ];
    }

    public static function isSecret(string $key): bool
    {
        return (bool) preg_match(self::SECRET, $key);
    }

    /**
     * A field in plain language: the label its form uses where there is one.
     * "title.fr" reads "Title (French)".
     */
    public static function label(string $key): string
    {
        if (preg_match('/^(.+)\.('.implode('|', Locales::SUPPORTED).')$/', $key, $m)) {
            return __('m3_retaud.audit_language', ['field' => self::label($m[1]), 'language' => Locales::nameOf($m[2])]);
        }

        foreach (['admin.field_'.$key, 'm3_retaud.field_'.$key] as $candidate) {
            if (Lang::has($candidate)) {
                return (string) __($candidate);
            }
        }

        return Str::ucfirst(str_replace('_', ' ', (string) preg_replace('/_id$/', '', $key)));
    }

    /** A stored value as text; null for nothing at all. */
    public static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null, $value === '', $value === [] => null,
            is_bool($value) => __($value ? 'm3_retaud.audit_yes' : 'm3_retaud.audit_no'),
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    public static function short(string $value): string
    {
        return Str::limit($value, self::SHORT, '...');
    }

    /**
     * The records the rows on one page are about, loaded once per type.
     * Trashed records count as deleted.
     *
     * @param  iterable<AuditLog>  $entries
     * @return array<string, Model>  keyed "type#id"
     */
    public static function subjects(iterable $entries): array
    {
        $ids = [];
        foreach ($entries as $entry) {
            if ($entry->auditable_type && $entry->auditable_id) {
                $ids[$entry->auditable_type][] = $entry->auditable_id;
            }
        }

        $found = [];
        foreach ($ids as $type => $list) {
            if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            $query = $type::query()->withoutGlobalScopes()->whereKey(array_unique($list));
            if (in_array(SoftDeletes::class, class_uses_recursive($type), true)) {
                $query->whereNull((new $type)->getQualifiedDeletedAtColumn());
            }

            foreach ($query->get() as $model) {
                $found[$type.'#'.$model->getKey()] = $model;
            }
        }

        return $found;
    }

    /**
     * The object column: what the row is about, a link to it while it
     * exists, and "deleted" once it does not.
     *
     * @param  array<string, Model>  $subjects
     * @return array{label: string, url: ?string, deleted: bool}|null
     */
    public static function subject(AuditLog $entry, array $subjects): ?array
    {
        if (! $entry->auditable_type) {
            return null;
        }

        $type = AuditLog::typeLabel($entry->auditable_type);

        if (! $entry->auditable_id) {
            return ['label' => $type, 'url' => null, 'deleted' => false];
        }

        $model = $subjects[$entry->auditable_type.'#'.$entry->auditable_id] ?? null;

        if ($model === null) {
            return ['label' => __('d3_i18n.audit_record', ['subject' => $type, 'id' => $entry->auditable_id]), 'url' => null, 'deleted' => true];
        }

        $name = self::nameOf($model);

        return [
            'label' => $name !== '' ? $type.': '.$name : __('d3_i18n.audit_record', ['subject' => $type, 'id' => $entry->auditable_id]),
            'url' => self::urlOf($model),
            'deleted' => false,
        ];
    }

    private static function nameOf(Model $model): string
    {
        if ($model instanceof User) {
            return (string) ($model->anonymised_at ? __('fa.former_user') : $model->name);
        }

        if ($model instanceof Submission) {
            return trim($model->period.', '.($model->country()->withoutGlobalScopes()->first()?->displayName() ?? ''), ', ');
        }

        foreach (ResourceRegistry::all() as $resource) {
            if ($resource->model === $model::class) {
                try {
                    return Str::limit($resource->titleFor($model), 60, '...');
                } catch (\Throwable) {
                    break;
                }
            }
        }

        foreach (['name', 'title', 'original_name', 'filename', 'slug', 'email'] as $column) {
            $value = $model->getAttribute($column);
            if (is_string($value) && $value !== '') {
                return Str::limit($value, 60, '...');
            }
        }

        return '';
    }

    private static function urlOf(Model $model): ?string
    {
        $locale = app()->getLocale();

        if ($model instanceof User && Route::has('admin.users.show')) {
            return route('admin.users.show', ['locale' => $locale, 'user' => $model->getKey()]);
        }

        if ($model instanceof Page && Route::has('admin.pages.edit') && filled($model->slug)) {
            return route('admin.pages.edit', ['locale' => $locale, 'page' => $model->slug]);
        }

        if ($model instanceof Submission && Route::has('admin.submissions.show')) {
            return route('admin.submissions.show', ['locale' => $locale, 'submission' => $model->getKey()]);
        }

        foreach (ResourceRegistry::all() as $resource) {
            if ($resource->model === $model::class) {
                return route('admin.resources.edit', ['locale' => $locale, 'resource' => $resource->key, 'id' => $model->getKey()]);
            }
        }

        // The back office's own edit screens, by the model's route key.
        $screens = [
            \App\Models\Meeting::class => ['admin.meetings.edit', 'meeting'],
            \App\Models\MediaAsset::class => ['admin.media.edit', 'asset'],
            \App\Models\Announcement::class => ['admin.announcements.edit', 'announcement'],
            \App\Models\SocialPost::class => ['admin.social.edit', 'post'],
            \App\Models\Report::class => ['admin.reports.show', 'report'],
            \App\Models\AuthorityChange::class => ['admin.authority-changes.show', 'change'],
            \App\Models\Folder::class => ['admin.documents.edit', 'folder'],
        ];

        if (isset($screens[$model::class]) && Route::has($screens[$model::class][0])) {
            [$name, $param] = $screens[$model::class];

            try {
                return route($name, ['locale' => $locale, $param => $model]);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Name search for the object filter: the (type, id) pairs whose name,
     * title or email contains the text, in the record's own columns or in
     * its translations.
     *
     * @param  Collection<int, string>  $types  full class names
     * @return array<string, list<int>>
     */
    public static function search(string $text, Collection $types): array
    {
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text).'%';
        $pairs = [];

        foreach ($types as $type) {
            if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            $model = new $type;
            $columns = array_values(array_intersect(
                ['name', 'title', 'email', 'slug', 'original_name', 'period'],
                \Illuminate\Support\Facades\Schema::getColumnListing($model->getTable())
            ));

            if ($columns !== []) {
                $ids = $type::query()->withoutGlobalScopes()
                    ->where(function ($q) use ($columns, $like) {
                        foreach ($columns as $column) {
                            $q->orWhere($column, 'like', $like);
                        }
                    })
                    ->limit(500)->pluck($model->getKeyName())->all();

                foreach ($ids as $id) {
                    $pairs[$type][] = (int) $id;
                }
            }
        }

        $translated = \App\Models\Translation::query()
            ->whereIn('translatable_type', $types->all())
            ->whereIn('field', ['title', 'name'])
            ->where('value', 'like', $like)
            ->limit(500)
            ->get(['translatable_type', 'translatable_id']);

        foreach ($translated as $t) {
            $pairs[$t->translatable_type][] = (int) $t->translatable_id;
        }

        return array_map(fn ($ids) => array_values(array_unique($ids)), $pairs);
    }
}
