<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Committee;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use App\Support\Locales;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Where the Secretariat writes an announcement. Extranet item 10.
 *
 * PUBLISHING IS A SEPARATE ACTION FROM SAVING, and it is the action that
 * notifies. An announcement that reached fourteen regulators the moment
 * somebody started typing it would make the whole channel untrustworthy
 * within a week.
 */
class AnnouncementAdminController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Announcement::class);

        return view('pages.admin.announcements.index', [
            'announcements' => Announcement::with(['translations', 'sector', 'committee', 'author'])
                ->orderByDesc('created_at')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Announcement::class);

        return view('pages.admin.announcements.form', [
            'announcement' => new Announcement,
            'sectors' => Sector::orderBy('name')->get(),
            'committees' => Committee::orderBy('name')->get(),
        ]);
    }

    public function edit(Announcement $announcement): View
    {
        Gate::authorize('update', $announcement);

        return view('pages.admin.announcements.form', [
            'announcement' => $announcement->load('translations'),
            'sectors' => Sector::orderBy('name')->get(),
            'committees' => Committee::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Announcement::class);

        $data = $this->validated($request);

        $announcement = Announcement::create([
            'author_id' => $request->user()->id,
            'sector_id' => $data['sector_id'] ?? null,
            'committee_id' => $data['committee_id'] ?? null,
            'slug' => $this->slug($data['title_en']),
            'status' => 'draft',
            'expires_on' => $data['expires_on'] ?? null,
            'is_sample' => false,
        ]);

        $this->writeText($announcement, $data);

        return redirect()
            ->route('admin.announcements.edit', ['locale' => app()->getLocale(), 'announcement' => $announcement->slug])
            ->with('status', __('announcements.saved'));
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        Gate::authorize('update', $announcement);

        $data = $this->validated($request);

        $announcement->update([
            'sector_id' => $data['sector_id'] ?? null,
            'committee_id' => $data['committee_id'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
        ]);

        $this->writeText($announcement, $data);

        return back()->with('status', __('announcements.saved'));
    }

    /**
     * Publish, and tell the people it is for.
     *
     * The notification goes out here and only here, so a draft edited six
     * times reaches nobody until somebody decides it is ready.
     */
    public function publish(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('publish', $announcement);

        $announcement->update(['status' => 'published', 'published_at' => now()]);

        foreach ($this->audience($announcement) as $user) {
            $user->notify(new AnnouncementPublished($announcement));
        }

        return back()->with('status', __('announcements.published'));
    }

    public function unpublish(Announcement $announcement): RedirectResponse
    {
        Gate::authorize('publish', $announcement);

        $announcement->update(['status' => 'draft']);

        return back()->with('status', __('announcements.unpublished'));
    }

    /**
     * Who this announcement is for.
     *
     * The same rule the reader's scope applies, run the other way round:
     * everybody, or the seats on one committee, or the states whose
     * authority holds one sector's mandate. A postal notice sent to a
     * broadcasting-only regulator is noise that trains people to stop
     * reading.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function audience(Announcement $announcement)
    {
        return User::query()
            ->where('status', 'active')
            ->whereIn('role', ['member_state_rep', 'committee_member'])
            ->get()
            ->filter(fn (User $user) => Announcement::query()
                ->whereKey($announcement->getKey())
                ->visibleTo($user)
                ->exists());
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $rules = [
            'sector_id' => ['nullable', 'exists:sectors,id'],
            'committee_id' => ['nullable', 'exists:committees,id'],
            'expires_on' => ['nullable', 'date'],
            'title_en' => ['required', 'string', 'max:200'],
            'body_en' => ['required', 'string', 'max:8000'],
        ];

        foreach (Locales::SUPPORTED as $locale) {
            if ($locale === 'en') {
                continue;
            }

            // An announcement may publish in English only. Requiring all
            // three would block the Secretariat from telling anybody
            // anything until a translator was free.
            $rules['title_'.$locale] = ['nullable', 'string', 'max:200'];
            $rules['body_'.$locale] = ['nullable', 'string', 'max:8000'];
        }

        return $request->validate($rules);
    }

    /** @param array<string, mixed> $data */
    private function writeText(Announcement $announcement, array $data): void
    {
        foreach (Locales::SUPPORTED as $locale) {
            $announcement->setTranslation('title', $locale, $data['title_'.$locale] ?? null);
            $announcement->setTranslation('body', $locale, $data['body_'.$locale] ?? null);
        }
    }

    private function slug(string $title): string
    {
        $base = Str::slug(Str::limit($title, 180, '')) ?: 'announcement';
        $slug = $base;
        $suffix = 2;

        while (Announcement::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
