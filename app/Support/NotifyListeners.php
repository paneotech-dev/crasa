<?php

namespace App\Support;

use App\Models\DelegateRegistration;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Listeners for events other parts of the platform fire.
 *
 * Registered by class name as a string, so nothing breaks while the event
 * class does not exist yet: a listener for an event nobody fires is inert.
 * Each one hands its event to the notifier, which applies the access check
 * and refuses duplicates.
 */
final class NotifyListeners
{
    public const DOCUMENT_ADDED = 'App\Events\DocumentAdded';

    public const REGISTRATION_CONFIRMED = 'App\Events\RegistrationConfirmed';

    public const REGISTRATION_RECEIVED = 'App\Events\RegistrationReceived';

    public static function register(): void
    {
        // public readonly $registration: the registered person's own
        // account, if any, and the Secretariat accounts that manage
        // delegate registrations.
        Event::listen(self::REGISTRATION_RECEIVED, function (object $event): void {
            $registration = $event->registration ?? null;

            if ($registration instanceof DelegateRegistration) {
                Notifier::registrationReceived($registration);
            }
        });

        // public readonly $document, $folder: everyone who may see that
        // folder, except the uploader.
        Event::listen(self::DOCUMENT_ADDED, function (object $event): void {
            $document = $event->document ?? null;

            if (! $document instanceof Document) {
                return;
            }

            $folder = ($event->folder ?? null) instanceof Folder ? $event->folder : null;
            $uploader = $document->uploaded_by !== null ? User::query()->find($document->uploaded_by) : null;

            Notifier::documentAdded($document, $folder, $uploader);
        });

        // public readonly $registration: the registered person's own
        // account, if any, and the member state's representatives.
        Event::listen(self::REGISTRATION_CONFIRMED, function (object $event): void {
            $registration = $event->registration ?? null;

            if ($registration instanceof DelegateRegistration) {
                Notifier::registrationConfirmed($registration);
            }
        });
    }
}
