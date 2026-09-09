<?php

namespace App\Admin;

/**
 * One control on a resource form.
 *
 * Deliberately small. A field says what it is and what it is called; it
 * does not know how to render itself or how to validate itself, because
 * both of those belong where the rest of the platform already does them:
 * in Blade, and in the resource's rules.
 */
final class Field
{
    /**
     * @param  list<string>|array<string, string>  $options
     */
    public function __construct(
        public readonly string $name,
        public readonly string $control = 'text',
        public readonly array $options = [],
        public readonly ?string $relation = null,
        public readonly bool $required = false,
        public readonly ?string $hint = null,
    ) {}

    public static function text(string $name, bool $required = false): self
    {
        return new self($name, required: $required);
    }

    public static function textarea(string $name, bool $required = false): self
    {
        return new self($name, 'textarea', required: $required);
    }

    public static function number(string $name, bool $required = false): self
    {
        return new self($name, 'number', required: $required);
    }

    public static function date(string $name, bool $required = false): self
    {
        return new self($name, 'date', required: $required);
    }

    public static function url(string $name, bool $required = false): self
    {
        return new self($name, 'url', required: $required);
    }

    public static function boolean(string $name): self
    {
        return new self($name, 'checkbox');
    }

    /** @param list<string> $options */
    public static function select(string $name, array $options, bool $required = false): self
    {
        return new self($name, 'select', options: $options, required: $required);
    }

    /** A foreign key, drawn as a select over the related records. */
    public static function belongsTo(string $name, string $relation, bool $required = false): self
    {
        return new self($name, 'relation', relation: $relation, required: $required);
    }
}
