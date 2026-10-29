<?php

namespace App\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One administered model, described once.
 *
 * Screen 09 states the rule this whole file exists to follow: "The
 * repeating resource table. Every content type in the back office uses this
 * pattern." Building a bespoke controller and a bespoke table per model
 * would produce fifteen surfaces that start identical and drift apart over
 * the length of the build, and the drift always lands on the least-used
 * ones, which on this platform are the reference tables a regulator's
 * country record lives in.
 *
 * So the shape is described here and rendered once. What is not generic
 * stays specific: pages, blocks, media and translations keep their own
 * controllers, because each does something a table cannot.
 */
final class Resource
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $columns  what the table shows
     * @param  list<Field>  $fields  what the form edits
     * @param  list<string>  $translatable  per-language fields
     * @param  array<string, mixed>  $rules  server-side validation
     * @param  list<string>  $searchable  columns the search box covers
     * @param  list<string>  $with  relations to eager load
     */
    public function __construct(
        public readonly string $key,
        public readonly string $model,
        public readonly array $columns,
        public readonly array $fields,
        public readonly array $rules,
        public readonly array $translatable = [],
        public readonly array $searchable = [],
        public readonly array $with = [],
        public readonly ?string $orderBy = null,
        public readonly bool $creatable = true,
        public readonly bool $deletable = true,
        /**
         * Reference data a regulator's records point at. It is editable and
         * it is never deletable from the interface: deleting the row for
         * Namibia does not remove Namibia from SADC, it orphans every
         * instrument, indicator value and user attached to it.
         */
        public readonly bool $isReferenceData = false,
        /**
         * Rules that depend on the record and on what was just typed.
         *
         * $rules above is a constant, which is right for "a name is
         * required and at most 200 characters" and cannot express "the
         * document this one supersedes must be of the same type and
         * earlier than the date in the form". The closure receives the
         * record being edited, or a blank model on create, and the request,
         * and its rules are merged over the constant ones.
         *
         * @var (\Closure(?Model, \Illuminate\Http\Request): array<string, mixed>)|null
         */
        public readonly ?\Closure $rulesUsing = null,
        // 'asc' or 'desc'. Dated content lists newest first (INC-02).
        public readonly string $orderDirection = 'asc',
        /*
         * List filters, drawn by the resource table's filter slot:
         * list<array{key: string, column: string, type: 'options'|'year', options?: array<string, string>}>
         * An 'options' filter offers value => translation key; a 'year'
         * filter offers the years present in the column.
         */
        public readonly array $filters = [],
        /*
         * Day 3. Columns that hold English text and take a French and a
         * Portuguese value beside it, kept in the translations table and
         * read by HasLocalisedNames on public pages. A member state's or an
         * indicator's name is a column every query sorts and joins on, so
         * the English stays where it is.
         *
         * @var list<string>
         */
        public readonly array $localised = [],
        /*
         * Day 3. What still points at a record, as label key => count. A
         * delete is refused while any count is above zero, and the message
         * names each of them. Null for a resource whose deletion nothing
         * blocks.
         *
         * @var (\Closure(Model): array<string, int>)|null
         */
        public readonly ?\Closure $references = null,
    ) {}

    /**
     * The records that still point at this one, without the zeroes.
     *
     * @return array<string, int>
     */
    public function referencesTo(Model $record): array
    {
        if ($this->references === null) {
            return [];
        }

        return array_filter(($this->references)($record), fn (int $count) => $count > 0);
    }

    /**
     * "This committee has 12 meetings and 3 workspace folders; move or
     * delete them first." Null when nothing points at the record.
     */
    public function refusalFor(Model $record): ?string
    {
        $references = $this->referencesTo($record);

        if ($references === []) {
            return null;
        }

        $parts = [];
        foreach ($references as $key => $count) {
            $parts[] = trans_choice('d3_crud.ref_'.$key, $count, ['count' => $count]);
        }

        $last = array_pop($parts);
        $list = $parts === [] ? $last : implode(', ', $parts).' '.__('d3_crud.and').' '.$last;

        return __('d3_crud.delete_refused', [
            'thing' => __('d3_crud.this_'.$this->key),
            'list' => $list,
        ]);
    }

    public function label(): string
    {
        return __('admin.resource_'.$this->key);
    }

    public function singular(): string
    {
        return __('admin.resource_'.$this->key.'_one');
    }

    /** Whether deleting is recoverable. Determines the restore path. */
    public function softDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->model), true);
    }

    /** @return Builder<Model> */
    public function query()
    {
        return $this->model::query();
    }

    public function titleFor(Model $record): string
    {
        // A name column first where there is one: a committee's first
        // translatable field is its mandate, a paragraph, and the typed
        // delete confirmation asks for the title (Day 3).
        if (filled($record->getAttribute('name')) && ! in_array('name', $this->translatable, true)) {
            return (string) $record->getAttribute('name');
        }

        if ($this->translatable !== [] && method_exists($record, 'translate')) {
            $title = $record->translate($this->translatable[0])?->value;

            if ($title !== null && trim($title) !== '') {
                return $title;
            }
        }

        foreach (['name', 'title', 'slug', 'reference_number', 'iso_code'] as $candidate) {
            if (filled($record->getAttribute($candidate))) {
                return (string) $record->getAttribute($candidate);
            }
        }

        return '#'.$record->getKey();
    }
}
