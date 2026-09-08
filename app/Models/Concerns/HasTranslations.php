<?php

namespace App\Models\Concerns;

use App\Models\Translation;
use App\Support\Locales;
use App\Support\ResolvedText;
use App\Support\TranslationResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
    public function translated(string $field, string $default = '', ?string $locale = null): string
    {
        return $this->translate($field, $locale)?->value ?? $default;
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

    public function isTranslatedInto(string $locale): bool
    {
        return in_array($locale, $this->translatedLocales(), true);
    }
}
