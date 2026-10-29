<?php

namespace App\Notifications\Concerns;

/**
 * What every notice in the bell carries besides its own fields.
 *
 * The category is the filter on the notifications page and the label in the
 * bell list (approval requests, decisions, validation results, documents,
 * meetings, registrations, announcements, other). The dedupe key is how
 * App\Support\Notifier refuses to tell one person about one event twice,
 * when two sources report the same thing (an upload in the member area and
 * the DocumentAdded event for that same document).
 *
 * Both are set by the notifier. A notice sent directly, as some tests do,
 * falls back to the class's own category.
 */
trait InApp
{
    public ?string $category = null;

    public ?string $dedupe = null;

    public function inApp(string $category, ?string $dedupe = null): static
    {
        $this->category = $category;
        $this->dedupe = $dedupe;

        return $this;
    }

    /** In-app only: mail stays off on this platform. */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    abstract protected function defaultCategory(): string;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withInApp(array $data): array
    {
        return $data + [
            'category' => $this->category ?? $this->defaultCategory(),
            'dedupe' => $this->dedupe,
        ];
    }
}
