<?php

namespace App\Enums;

/**
 * The five roles from DATA_MODEL section 7.
 *
 * Screen 13 draws a matrix of four; committee_member is not on it, because
 * the screen shows the four roles that have distinct permission rows.
 * committee_member reads what a viewer reads and adds documents at its
 * committee's clearance level, which the matrix expresses as a document
 * grade rather than a column.
 */
enum Role: string
{
    case Administrator = 'administrator';
    case Editor = 'editor';
    case MemberStateRep = 'member_state_rep';
    case CommitteeMember = 'committee_member';
    case Viewer = 'viewer';
    case Moderator = 'moderator';
    case Auditor = 'auditor';

    public function label(): string
    {
        // In the reader's language: the role appears in invitations, the
        // users list and flashes, and used to stay English on /fr and /pt.
        return __('roles.'.$this->value);
    }

    /**
     * 2FA is mandatory for administrators and enforced at login, not offered
     * in settings. See CLAUDE.md and DATA_MODEL section 7.
     *
     * Switchable for a demonstration: CRASA_TWO_FACTOR_REQUIRED=false in .env
     * (config crasa.two_factor_required) lifts the requirement for every
     * account. Remove the line, or set it to true, and clear the config cache
     * to require it again. An account that has enrolled is still asked for
     * its code either way.
     */
    public function requiresTwoFactor(): bool
    {
        return $this === self::Administrator && (bool) config('crasa.two_factor_required', true);
    }

    /**
     * Roles that read and write across every member state. Everyone else is
     * held to their own country by the global scope.
     */
    public function seesAllCountries(): bool
    {
        return in_array($this, [self::Administrator, self::Editor], true);
    }
}
