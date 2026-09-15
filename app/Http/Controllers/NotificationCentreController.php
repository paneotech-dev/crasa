<?php

namespace App\Http\Controllers;

use App\Enums\DigestFrequency;
use App\Enums\NotificationTrigger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The notification centre, behind the bell. RP-05, extranet item 6.
 *
 * WHY A PAGE AND NOT A DROPDOWN. The bell pointed at an anchor on the
 * account page, where the five most recent notices sat under the password
 * form. A regulator coming back after a fortnight has more than five and
 * needs to page through them, mark them read and change what they hear
 * about, and none of that fits in a panel that closes when you click
 * outside it.
 *
 * MARKING READ HAPPENS ON THE WAY THROUGH. Opening a notification marks it
 * and then goes where it points, so a reader never has to do both. The
 * redirect target is the notification's own stored URL, checked against
 * this host, because a stored URL is data and an open redirect is an open
 * redirect however it got there.
 */
class NotificationCentreController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pages.notifications.index', [
            'notifications' => $user->notifications()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
            'triggers' => NotificationTrigger::cases(),
            'chosen' => $user->notification_preferences ?? NotificationTrigger::defaults(),
            'frequency' => $user->digest_frequency ?? DigestFrequency::Daily->value,
            'frequencies' => DigestFrequency::cases(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        $target = $record->data['url'] ?? null;

        /*
         * Only a path on this host. The stored URL is data written by a
         * sender, and a sender that could be persuaded to write
         * "https://elsewhere" would have an open redirect behind an
         * authenticated route.
         */
        if (! is_string($target) || ! str_starts_with($target, url('/'))) {
            return redirect()->route('notifications', ['locale' => app()->getLocale()]);
        }

        return redirect()->to($target);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('notifications.all_read'));
    }

    public function preferences(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'triggers' => ['array'],
            'triggers.*' => ['string', 'in:'.implode(',', NotificationTrigger::values())],
            'digest_frequency' => ['required', 'string', 'in:daily,weekly,never'],
        ]);

        $request->user()->update([
            'notification_preferences' => array_values($data['triggers'] ?? []),
            'digest_frequency' => $data['digest_frequency'],
        ]);

        return back()->with('status', __('notifications.preferences_saved'));
    }
}
