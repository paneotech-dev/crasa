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
    ) {}

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
