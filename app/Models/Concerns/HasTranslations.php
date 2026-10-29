<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use App\Support\Locales;
use App\Support\ResolvedText;
use App\Support\TranslationResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Translatable text for a model, resolved through the DATA_MODEL section 5
 * chain: requested locale, then English, then null with a log.
 *
 * A model declares its translatable fields:
 *
 *     protected array $translatable = ['title', 'summary', 'body'];
 *
 * Reading is $model->translate('title'), which returns the ResolvedText the
 * fallback notice component already understands, so nothing downstream
 * changes when a record gains or loses a translation.
 *
 * Eager loading is not optional. withTranslations() on a listing query pulls
 * every translation for every record in one query. Without it a list of
 * fifty publications with three fields each fires a query per field per
 * record, and the page dies on the member state with the most publications.
 */
trait HasTranslations
{
    /**
     * Take the translations with the record.
     *
     * Translations are polymorphic, so there is no foreign key and nothing
     * cascades. Deleting a publication left its French and Portuguese title
     * behind for ever, and the go-live purge left 337 of them.
     *
     * Dead rows are the smaller half of it. The row is addressed by
     * translatable_type and translatable_id, and ids are reused: delete
     * publication 5 and create a new one, and the new publication inherits
     * the dead one's French title. After the purge, the first publication
     * CRASA created could have shown a demonstration title in another
     * language, on a platform whose whole claim is that its content is
     * sourced.
     *
     * A soft delete keeps them, because a soft-deleted record can be
     * restored and would come back mute. Only a real deletion takes them.
     */
    public static function bootHasTranslations(): void
    {
        static::deleted(function ($model) {
            $softDeleting = in_array(
                SoftDeletes::class,
                class_uses_recursive($model),
                true
            );

            if ($softDeleting && ! $model->isForceDeleting()) {
                return;
            }

            $model->translations()->delete();
        });
    }

    /**
     * Delete these records and their translations.
     *
     * `Model::query()->delete()` and `$parent->children()->delete()` are
     * mass deletes: they issue one DELETE and fire no model events, so the
     * hook above never runs and every translation is orphaned. Reseeding
     * this platform four times in one afternoon left 525 of them.
     *
     * Any code clearing translatable records in bulk uses this instead.
     */
    public function scopePurge(Builder $query): int
    {
        $ids = (clone $query)->pluck($this->getKeyName());

        if ($ids->isEmpty()) {
            return 0;
        }

        Translation::query()
            ->where('translatable_type', $this->getMorphClass())
            ->whereIn('translatable_id', $ids)
            ->delete();

        return $query->delete();
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /** @return list<string> */
    public function translatableFields(): array
    {
        return $this->translatable ?? [];
    }

    public function scopeWithTranslations(Builder $query): Builder
    {
        return $query->with('translations');
    }

    /**
     * Resolve one field. Null means the field has no text in the requested
     * language or in English, which is logged and rendered as nothing rather
     * than as an empty heading.
     */
    public function translate(string $field, ?string $locale = null): ?ResolvedText
    {
        // A record not saved yet (an empty "new" form) has no text to find.
        if (! $this->exists && ! $this->relationLoaded('translations')) {
            return null;
        }

        return TranslationResolver::resolve(
            $this->translationValuesFor($field),
            $locale,
            [
                'field' => $field,
                'translatable' => $this->getMorphClass().'#'.$this->getKey(),
            ]
        );
    }

    /** The resolved string, or a fallback string, for templates that want plain text. */
    /**
     * The default is nullable because callers pass a nullable column as the
     * fallback — reference_number, most often — and a string-only parameter
     * turned every record without one into a TypeError and a 500. Found on
     * the rebuilt host on 16 September: the members' register failed for all
     * ten seeded instruments, none of which carries a reference number.
     */
    public function translated(string $field, ?string $default = '', ?string $locale = null): string
    {
        return $this->translate($field, $locale)?->value ?? ($default ?? '');
    }

    /**
     * Every locale's value for one field, keyed by locale. Reads the loaded
     * relation when it is there, so an eager-loaded listing fires no further
     * queries.
     *
     * @return array<string, string|null>
     */
    public function translationValuesFor(string $field): array
    {
        return $this->loadedTranslations()
            ->where('field', $field)
            ->mapWithKeys(fn (Translation $t) => [$t->locale => $t->value])
            ->all();
    }

    /** @return Collection<int, Translation> */
    private function loadedTranslations(): Collection
    {
        return $this->relationLoaded('translations')
            ? $this->getRelation('translations')
            : $this->translations()->get();
    }

    /**
     * Write one field in one language. Passing null or an empty string
     * removes the translation rather than storing a blank, because a blank
     * would satisfy the resolver and render an empty heading.
     */
    public function setTranslation(string $field, string $locale, ?string $value, ?int $translatedBy = null): static
    {
        abort_unless(Locales::isSupported($locale), 500, 'Unsupported locale: '.$locale);

        $attributes = [
            'translatable_type' => $this->getMorphClass(),
            'translatable_id' => $this->getKey(),
            'locale' => $locale,
            'field' => $field,
        ];

        if ($value === null || trim($value) === '') {
            Translation::where($attributes)->delete();
        } else {
            Translation::updateOrCreate($attributes, [
                'value' => $value,
                'translated_by' => $translatedBy,
                // A person has written this string, so it is no longer a
                // draft that came from a machine. Screen 05's "Rewrite":
                // the flag is cleared by the act of writing, not by a
                // separate step somebody has to remember.
                'is_machine_draft' => false,
            ]);
        }

        $this->unsetRelation('translations');

        return $this;
    }

    /**
     * Write several fields for one language at once.
     *
     * @param  array<string, string|null>  $values
     */
    public function setTranslations(string $locale, array $values, ?int $translatedBy = null): static
    {
        foreach ($values as $field => $value) {
            $this->setTranslation($field, $locale, $value, $translatedBy);
        }

        return $this;
    }

    /**
     * Which languages this record is complete in, for the translation editor
     * and for the language badge on a document row.
     *
     * @return list<string>
     */
    public function translatedLocales(): array
    {
        $required = $this->translatableFields();

        if ($required === []) {
            return [];
        }

        $byLocale = $this->loadedTranslations()
            ->filter(fn (Translation $t) => is_string($t->value) && trim($t->value) !== '')
            ->groupBy('locale');

        return $byLocale
            ->filter(fn (Collection $rows) => $rows->pluck('field')->intersect($required)->count() === count($required))
            ->keys()
            ->values()
            ->all();
    }

    /**
     * True when any of these fields is shown to the reader in a language
     * whose text is still a machine draft: the field is resolved as a page
     * would resolve it (requested language, then English) and the flag is
     * read on the row that supplies the text.
     *
     * @param  list<string>  $fields
     */
    public function showsMachineDraft(array $fields, ?string $locale = null): bool
    {
        foreach ($fields as $field) {
            $resolved = $this->translate($field, $locale);

            if ($resolved === null) {
                continue;
            }

            $row = $this->loadedTranslations()
                ->first(fn (Translation $t) => $t->field === $field && $t->locale === $resolved->locale);

            if ($row?->is_machine_draft) {
                return true;
            }
        }

        return false;
    }

    public function isTranslatedInto(string $locale): bool
    {
        return in_array($locale, $this->translatedLocales(), true);
    }
}
