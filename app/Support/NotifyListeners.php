<?php

namespace App\Support;

use App\Models\DelegateRegistration;
use App\Models\Document;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Listeners for events other parts of the platform fire.
 *
 * Registered by class name as a string, so nothing breaks while the event
 * class does not exist yet: a listener for an event nobody fires is inert.
 * Each one hands its event to the notifier, which applies the access check
 * and refuses duplicates. A model deleted (or moved to the bin) before the
 * listener runs is nothing to announce, so each is read again first.
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
            $registration = self::current($event->registration ?? null);

            if ($registration instanceof DelegateRegistration) {
                Notifier::registrationReceived($registration);
            }
        });

        // public readonly $document, $folder: everyone who may see that
        // folder, except the uploader.
        Event::listen(self::DOCUMENT_ADDED, function (object $event): void {
            $document = self::current($event->document ?? null);

            if (! $document instanceof Document) {
                return;
            }

            $folder = self::current($event->folder ?? null);
            $folder = $folder instanceof Folder ? $folder : null;

            // A file whose folder has gone is not announced either.
            if ($folder === null && $document->folder_id !== null) {
                $folder = self::current((new Folder)->newQueryWithoutScopes()->find($document->folder_id));

                if ($folder === null) {
                    return;
                }
            }
            $uploader = $document->uploaded_by !== null ? User::query()->find($document->uploaded_by) : null;

            Notifier::documentAdded($document, $folder, $uploader);
        });

        // public readonly $registration: the registered person's own
        // account, if any, and the member state's representatives.
        Event::listen(self::REGISTRATION_CONFIRMED, function (object $event): void {
            $registration = self::current($event->registration ?? null);

            if ($registration instanceof DelegateRegistration) {
                Notifier::registrationConfirmed($registration);
            }
        });
    }

    /**
     * The model as it stands now, or null when its row is gone or in the
     * bin. Read without global scopes: a listener has no reader.
     */
    private static function current(mixed $model): ?Model
    {
        if (! $model instanceof Model || ! $model->exists || $model->getKey() === null) {
            return null;
        }

        $fresh = $model->newQueryWithoutScopes()->whereKey($model->getKey())->first();

        return $fresh !== null && method_exists($fresh, 'trashed') && $fresh->trashed() ? null : $fresh;
    }
}
