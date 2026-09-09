<?php

namespace App\Enums;

/**
 * The editorial states a page moves through.
 *
 * Four, not two. "Draft" and "published" alone force a reviewer to either
 * publish something to look at it or take the author's word for it, and the
 * platform has a separate role for review precisely because the Secretariat
 * does not do the second.
 *
 * Archived is not deleted. A page that came off the site is still a page
 * somebody may have cited.
 */
enum PageStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
    case Archived = 'archived';

    public function labelKey(): string
    {
        return 'admin.page_status_'.$this->value;
    }

    /** Whether the public site renders a page in this state. */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /**
     * The states this one may move to. Not every state reaches every other:
     * a draft goes to review, not straight to the site, unless the person
     * moving it may publish, which the policy decides separately.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Review, self::Published],
            self::Review => [self::Draft, self::Published],
            self::Published => [self::Draft, self::Archived],
            self::Archived => [self::Draft],
        };
    }

    /** Whether reaching this state is a publishing act. */
    public function requiresPublishPermission(): bool
    {
        return $this === self::Published || $this === self::Archived;
    }
}
