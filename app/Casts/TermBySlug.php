<?php

namespace App\Casts;

use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A column holding a taxonomy slug, read as the term itself.
 *
 * news_items.category and publications.publication_type keep the slug
 * they always held (it is what the public filter URLs carry), and the
 * model hands back the NewsCategory or PublicationType row, so a template
 * asks it for its label and colour. Written as a term, a slug or, from
 * older callers, a backed enum.
 *
 * @implements CastsAttributes<Model|null, mixed>
 */
final class TermBySlug implements CastsAttributes
{
    /** @param class-string<Model> $term */
    public function __construct(private readonly string $term) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Model
    {
        return $value === null ? null : ($this->term)::bySlug((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Model => (string) $value->getAttribute('slug'),
            $value instanceof BackedEnum => (string) $value->value,
            default => (string) $value,
        };
    }
}
