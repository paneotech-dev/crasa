<?php

namespace App\Enums;

/**
 * A seat on a committee, held by a member state through committee_country
 * and by a person through committee_user.
 */
enum CommitteeRole: string
{
    case Member = 'member';
    case Chair = 'chair';
    case Observer = 'observer';

    public function label(): string
    {
        return __('d3_i18n.committee_role_'.$this->value);
    }

    /**
     * An observer attends but does not hold the committee's confidential
     * papers. Members and chairs do.
     */
    public function opensConfidentialDocuments(): bool
    {
        return $this !== self::Observer;
    }
}
