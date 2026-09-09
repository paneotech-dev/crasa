<?php

namespace App\Rules;

use App\Models\Publication;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * What a publication may be recorded as superseding.
 *
 * The picker only offers valid candidates, and the picker is a convenience.
 * These four are the rule, checked on the server on every write:
 *
 *   it is not itself, because a document cannot replace itself;
 *   it is the same publication type, because a guideline replacing an
 *     annual report is a data error rather than a lineage;
 *   its date is earlier, because supersession runs one way in time;
 *   it does not already sit later in this document's own chain, which is
 *     what turns a lineage into a loop and hangs the renderer.
 *
 * The message says which of the four failed. "The selected supersedes is
 * invalid" tells an editor nothing they can act on.
 */
class SupersedesRule implements ValidationRule
{
    public function __construct(
        private readonly ?Publication $record,
        private readonly ?string $type,
        private readonly ?string $publishedOn,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $earlier = Publication::withTrashed()->find($value);

        if ($earlier === null) {
            $fail(__('admin.supersedes_missing'));

            return;
        }

        if ($this->record !== null && $earlier->getKey() === $this->record->getKey()) {
            $fail(__('admin.supersedes_itself'));

            return;
        }

        // publication_type is cast to an enum on the model and arrives
        // from the form as a string. Compared as values, not as objects.
        $earlierType = $earlier->publication_type instanceof \BackedEnum
            ? $earlier->publication_type->value
            : (string) $earlier->publication_type;

        if ($this->type !== null && $earlierType !== $this->type) {
            $fail(__('admin.supersedes_type'));

            return;
        }

        if ($this->publishedOn !== null && $earlier->published_on !== null
            && $earlier->published_on->toDateString() >= $this->publishedOn) {
            $fail(__('admin.supersedes_later', ['date' => $earlier->published_on->isoFormat('D MMMM YYYY')]));

            return;
        }

        if ($this->record !== null && $this->wouldLoop($earlier)) {
            $fail(__('admin.supersedes_loop'));
        }
    }

    /**
     * Whether the candidate already sits later in this record's own chain.
     *
     * Walked rather than reasoned about, with the same guard the model's
     * lineage() uses: a chain that is already broken must not hang the
     * validator on its way to reporting that it is broken.
     */
    private function wouldLoop(Publication $earlier): bool
    {
        $seen = [$this->record->getKey()];
        $cursor = $earlier;
        $guard = 0;

        while ($cursor !== null && $guard++ < 20) {
            if (in_array($cursor->getKey(), $seen, true)) {
                return true;
            }

            $seen[] = $cursor->getKey();
            $cursor = $cursor->supersedes;
        }

        return false;
    }
}
