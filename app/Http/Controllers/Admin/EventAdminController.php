<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Clearance;
use App\Http\Controllers\Controller;
use App\Models\Committee;
use App\Models\Document;
use App\Models\Meeting;
use App\Support\EventGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Events in the back office (E4): the form in four steps and a summary,
 * cancel or postpone with the status shown on the public page, edit and
 * delete with a confirmation.
 *
 * It records what a meeting is, when and where, who can see it and its
 * documents. It books nothing: no travel, rooms or transport.
 */
class EventAdminController extends Controller
{
    public const KINDS = ['committee', 'workshop', 'governance', 'summit', 'webinar'];

    public function create(): View
    {
        Gate::authorize('create', Meeting::class);

        return $this->form(new Meeting([
            'clearance' => Clearance::Public,
            'kind' => 'committee',
            'timezone' => \App\Support\LocalTime::zone(),
            'status' => 'scheduled',
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Meeting::class);

        $data = $this->validated($request);

        $meeting = DB::transaction(function () use ($data, $request) {
            $meeting = Meeting::create($data + [
                'slug' => $this->slugFor($data['title'], $data['starts_on']),
                'source' => 'admin',
                'status' => 'scheduled',
                'date_unknown' => false,
            ]);
            $this->attachFiles($request, $meeting);

            return $meeting;
        });

        return redirect()
            ->route('admin.meetings.edit', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('e.admin_created'));
    }

    public function edit(Meeting $meeting): View
    {
        Gate::authorize('update', $meeting);

        return $this->form($meeting->load('documents'));
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $request, $meeting) {
            $meeting->update($data + ['date_unknown' => false]);

            $remove = array_map('intval', (array) $request->input('remove_documents', []));
            if ($remove !== []) {
                $meeting->documents()->whereIn('id', $remove)->get()->each->delete();
            }

            $this->attachFiles($request, $meeting);
        });

        return redirect()
            ->route('admin.meetings.edit', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('e.admin_saved'));
    }

    /** Cancel, postpone or restore. The public page shows the status. */
    public function status(Request $request, Meeting $meeting): RedirectResponse
    {
        Gate::authorize('update', $meeting);

        $data = $request->validate([
            'status' => ['required', Rule::in(Meeting::STATUSES)],
            'status_note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'status' => __('e.field_status'),
            'status_note' => __('e.field_status_note'),
        ]);

        $meeting->update([
            'status' => $data['status'],
            'status_note' => $data['status'] === 'scheduled' ? null : ($data['status_note'] ?? null),
        ]);

        return redirect()
            ->route('admin.meetings.edit', ['locale' => app()->getLocale(), 'meeting' => $meeting->slug])
            ->with('status', __('e.status_saved'));
    }

    /** The confirmation as a page, for a browser that does not open the dialog. */
    public function confirmDelete(Meeting $meeting): View
    {
        Gate::authorize('delete', $meeting);

        return view('pages.admin.events.delete', ['meeting' => $meeting->loadCount('delegateRegistrations')]);
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        Gate::authorize('delete', $meeting);

        DB::transaction(function () use ($meeting) {
            $meeting->documents()->get()->each->delete();
            $meeting->delete();
        });

        return redirect()
            ->route('admin.meetings', ['locale' => app()->getLocale()])
            ->with('status', __('e.admin_deleted'));
    }

    private function form(Meeting $meeting): View
    {
        return view('pages.admin.events.form', [
            'meeting' => $meeting,
            'groups' => array_map(fn ($code) => EventGroup::code($code), array_keys(EventGroup::GROUPS)),
            'committees' => Committee::orderBy('name')->get(['id', 'name']),
            'kinds' => self::KINDS,
            'clearances' => [Clearance::Public, Clearance::Member, Clearance::Confidential, Clearance::Secretariat],
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'committee_code' => ['nullable', Rule::in(array_keys(EventGroup::GROUPS))],
            'committee_id' => ['nullable', 'integer', 'exists:committees,id'],
            'kind' => ['required', Rule::in(self::KINDS)],
            'summary' => ['nullable', 'string', 'max:2000'],
            'organiser' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'required_with:starts_at'],
            'timezone' => ['nullable', 'timezone:all'],
            'location' => ['nullable', 'string', 'max:255'],
            'venue' => ['nullable', 'string', 'max:200'],
            'venue_country' => ['nullable', 'string', 'max:120'],
            'online_url' => ['nullable', 'url:https,http', 'max:255'],
            'clearance' => ['required', Rule::enum(Clearance::class)],
            'registration_url' => ['nullable', 'url:https,http', 'max:255'],
            'registration_opens_on' => ['nullable', 'date'],
            'registration_closes_on' => ['nullable', 'date', 'after_or_equal:registration_opens_on'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:65000'],
            'agenda' => ['nullable', 'string', 'max:20000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx', 'max:20480'],
            'remove_documents' => ['nullable', 'array'],
        ], [
            'ends_on.after_or_equal' => __('e.error_ends_on'),
            'registration_closes_on.after_or_equal' => __('e.error_closes_on'),
            'ends_at.required_with' => __('e.error_ends_at'),
        ], $this->attributes());

        unset($data['files'], $data['remove_documents']);

        if (! empty($data['starts_at']) && ! empty($data['ends_at'])
            && ($data['ends_on'] ?? $data['starts_on']) === $data['starts_on'] && $data['ends_at'] <= $data['starts_at']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['ends_at' => __('e.error_ends_at_order')]);
        }

        return $data;
    }

    /** @return array<string, string> */
    private function attributes(): array
    {
        $names = [];
        foreach (['title', 'committee_code', 'committee_id', 'kind', 'summary', 'organiser', 'starts_on', 'ends_on', 'starts_at', 'ends_at', 'timezone', 'location', 'venue', 'online_url', 'clearance', 'registration_url', 'registration_opens_on', 'registration_closes_on', 'capacity', 'agenda'] as $field) {
            $key = $field === 'committee_code' ? 'e.field_code' : ($field === 'committee_id' ? 'e.field_committee' : ($field === 'venue_country' ? 'e.field_country' : 'e.field_'.$field));
            $names[$field] = __($key);
        }
        $names['venue_country'] = __('e.field_country');
        $names['files.*'] = __('e.field_files');

        return $names;
    }

    private function slugFor(string $title, string $date): string
    {
        $base = Str::limit(Str::slug($title), 140, '').'-'.substr($date, 0, 4);
        $slug = $base;
        $n = 2;
        while (Meeting::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    private function attachFiles(Request $request, Meeting $meeting): void
    {
        foreach ((array) $request->file('files', []) as $file) {
            $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'document';
            $path = 'events/'.$meeting->id.'/'.$name.'-'.Str::random(8).'.'.strtolower($file->getClientOriginalExtension());
            Storage::disk('local')->put($path, file_get_contents($file->getRealPath()));

            Document::create([
                'documentable_type' => Meeting::class,
                'documentable_id' => $meeting->id,
                'committee_id' => $meeting->committee_id,
                'uploaded_by' => $request->user()->id,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'clearance' => $meeting->clearance,
            ]);
        }
    }
}
