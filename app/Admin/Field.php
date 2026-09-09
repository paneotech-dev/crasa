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
    /**
     * @param  list<string>|array<string, string>  $options
     * @param  (\Closure(?\Illuminate\Database\Eloquent\Model): array<int|string, string>)|null  $optionsUsing
     */
    public function __construct(
        public readonly string $name,
        public readonly string $control = 'text',
        public readonly array $options = [],
        public readonly ?string $relation = null,
        public readonly bool $required = false,
        public readonly ?string $hint = null,
        public readonly ?\Closure $optionsUsing = null,
        public readonly bool $readOnly = false,
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

    /**
     * A foreign key whose candidates depend on the record being edited.
     *
     * belongsTo() lists every row of the related table, which is right for
     * a sector and wrong for supersession: a publication may only be
     * superseded by one of the same type with an earlier date, and never
     * by itself. The closure receives the record and returns the
     * candidates, so the rule lives beside the field rather than in the
     * controller.
     *
     * @param  \Closure(?\Illuminate\Database\Eloquent\Model): array<int|string, string>  $options
     */
    public static function scoped(string $name, \Closure $options, ?string $hint = null): self
    {
        return new self($name, 'relation', optionsUsing: $options, hint: $hint);
    }

    /**
     * A value the form shows and does not let anybody type.
     *
     * "Superseded by" is the reverse of a relation somebody else owns:
     * it is set by editing the later document, not this one, and a second
     * writable control for the same fact is how the two ends stop agreeing.
     *
     * @param  \Closure(?\Illuminate\Database\Eloquent\Model): string  $value
     */
    public static function readOnly(string $name, \Closure $value, ?string $hint = null): self
    {
        return new self($name, 'readonly', optionsUsing: $value, hint: $hint, readOnly: true);
    }
}
