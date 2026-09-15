<?php

namespace App\Enums;

/**
 * What the platform will tell a member about. RP-05.
 *
 * A CLOSED SET, AND THAT IS THE POINT. Every trigger below is about
 * regulatory content: an instrument, a return, a document, a meeting's
 * existence, a comment on a paper, a notice from the Secretariat. Nothing
 * here is about a journey, a room, a transfer or a meal, and adding one
 * would need a new case in this enum, a new label in three languages and a
 * change to the test that reads this file. That is the friction it is meant
 * to have: event and travel management is reserved commercial scope.
 *
 * "A meeting scheduled or changed" is in scope because it is a fact about
 * the committee's calendar. Reminding somebody to catch a flight to it is
 * not.
 */
enum NotificationTrigger: string
{
    /** A new instrument or guideline in a sector this reader's authority regulates. */
    case SectorInstrument = 'sector_instrument';

    /** This reader's own return was validated or sent back. */
    case SubmissionDecision = 'submission_decision';

    /** A document was filed into a committee this reader sits on. */
    case CommitteeDocument = 'committee_document';

    /** A meeting was scheduled, moved or cancelled. */
    case MeetingChanged = 'meeting_changed';

    /** Somebody replied on a document thread this reader is in. */
    case ThreadComment = 'thread_comment';

    /** The Secretariat published an announcement. */
    case Announcement = 'announcement';

    public function labelKey(): string
    {
        return 'notifications.trigger_'.$this->value;
    }

    public function descriptionKey(): string
    {
        return 'notifications.trigger_'.$this->value.'_note';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * What a new account is subscribed to.
     *
     * Everything except the two highest-volume triggers. A regulator whose
     * first week produces forty emails turns the channel off and then
     * misses the one that mattered.
     */
    public static function defaults(): array
    {
        return [
            self::SubmissionDecision->value,
            self::Announcement->value,
            self::MeetingChanged->value,
        ];
    }
}
