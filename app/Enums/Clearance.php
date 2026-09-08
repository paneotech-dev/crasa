<?php

namespace App\Enums;

/**
 * Document and folder confidentiality.
 *
 * The vocabulary is the one CRASA has seen and accepted, drawn in screen 01
 * as the confidentiality badge set and granted in screen 13's matrix. It
 * replaces the public / member / committee / restricted set that
 * DATA_MODEL originally carried, which was never shown to the client.
 *
 * Access to Confidential derives from a user's role in the committee that
 * owns the document, not from a per-user grant list.
 */
enum Clearance: string
{
    /** Anyone, signed in or not. */
    case Public = 'public';

    /** Any signed-in member state or Secretariat account. */
    case Member = 'member';

    /** The owning committee, and administrators. */
    case Confidential = 'confidential';

    /** Secretariat administrators only. */
    case Secretariat = 'secretariat';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Member => 'Member',
            self::Confidential => 'Confidential',
            self::Secretariat => 'Secretariat',
        };
    }

    public function requiresAuthentication(): bool
    {
        return $this !== self::Public;
    }

    /** Ordering, so a comparison reads as "at least this clearance". */
    public function rank(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Member => 1,
            self::Confidential => 2,
            self::Secretariat => 3,
        };
    }

    /** The permission that opens a document at this grade, where one exists. */
    public function permission(): ?Permission
    {
        return match ($this) {
            self::Public => null,
            self::Member => Permission::OpenMemberDocuments,
            self::Confidential => Permission::OpenConfidentialDocuments,
            self::Secretariat => Permission::OpenSecretariatDocuments,
        };
    }
}
