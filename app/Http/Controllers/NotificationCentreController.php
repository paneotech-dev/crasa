<?php

namespace App\Http\Controllers;

use App\Enums\DigestFrequency;
use App\Enums\NotificationTrigger;
use App\Support\Locales;
use App\Support\Notifier;
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

        // Day 3: filters by type and by read state, in the query string so
        // a filtered list can be bookmarked and works with no script.
        $type = in_array($request->query('type'), Notifier::CATEGORIES, true) ? $request->query('type') : null;
        $state = in_array($request->query('state'), ['unread', 'read'], true) ? $request->query('state') : null;

        $query = $user->notifications();

        if ($type !== null) {
            $query->where('data->category', $type);
        }

        if ($state === 'unread') {
            $query->whereNull('read_at');
        } elseif ($state === 'read') {
            $query->whereNotNull('read_at');
        }

        return view('pages.notifications.index', [
            'notifications' => $query->paginate(20)->withQueryString(),
            'type' => $type,
            'state' => $state,
            'categories' => Notifier::CATEGORIES,
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

        return redirect()->to(self::inCurrentLanguage($target));
    }

    /** Mark one notice read without opening it (Day 3, the bell list). */
    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return back()->with('status', __('d3_notify.marked_read'));
    }

    /**
     * A stored link carries the language the notice was written in. The
     * reader opens it in the language they are reading now.
     */
    private static function inCurrentLanguage(string $url): string
    {
        $base = url('/');
        $path = substr($url, strlen($base));

        if (preg_match('~^/([a-z]{2})(/|$)~', $path, $m) && Locales::isSupported($m[1])) {
            return $base.'/'.app()->getLocale().substr($path, 3);
        }

        return $url;
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
