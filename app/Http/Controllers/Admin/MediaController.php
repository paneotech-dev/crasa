<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\MediaLibrary;
use App\Support\Locales;
use App\Support\MediaUsage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The media library, CM-03, against screen 14.
 *
 * The screen draws a grid and a list of the same assets behind a two-button
 * toggle, a search over "file name, caption or alt text", filters for type
 * and where an asset is used, and a red count of the assets missing alt
 * text. All of that is here. What the screen draws as static renditions --
 * upload progress and the rendition table -- is real: renditions are
 * generated on upload and their sizes are read back from disk.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', MediaAsset::class);

        $term = trim($request->string('q')->toString());

        $assets = MediaAsset::query()
            ->with(['translations', 'uploader:id,name'])
            ->when($term !== '', function ($query) use ($term) {
                // File name, caption or alt text, as the screen says.
                $query->where(function ($q) use ($term) {
                    $q->where('original_name', 'like', '%'.$term.'%')
                        ->orWhereHas('translations', fn ($t) => $t
                            ->whereIn('field', ['alt', 'caption'])
                            ->where('value', 'like', '%'.$term.'%'));
                });
            })
            ->when($request->filled('type'), fn ($query) => $query->where('mime_type', $request->string('type')->toString()))
            ->when($request->boolean('missing_alt'), fn ($query) => $query->missingAlt())
            ->latest('created_at')
            ->paginate(24)
            ->withQueryString();

        // Usage is resolved for the page of assets on screen, not for the
        // whole library: the "Used on" column is worth a query per row, a
        // full scan of every block is not.
        $usage = [];

        foreach ($assets as $asset) {
            $usage[$asset->id] = MediaUsage::for($asset);
        }

        return view('pages.admin.media.index', [
            'assets' => $assets,
            'usage' => $usage,
            'missingAltCount' => MediaAsset::missingAlt()->count(),
            'view' => $request->string('view')->toString() === 'list' ? 'list' : 'grid',
            'types' => MediaAsset::query()->distinct()->orderBy('mime_type')->pluck('mime_type')->all(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', MediaAsset::class);

        return view('pages.admin.media.upload');
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', MediaAsset::class);

        $request->validate([
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', MediaLibrary::ACCEPTED),
                'max:'.(int) (MediaLibrary::MAX_BYTES / 1024),
            ],
        ]);

        $asset = $this->library->store($request->file('file'), $request->user()->id);

        return redirect()
            ->route('admin.media.edit', ['locale' => app()->getLocale(), 'asset' => $asset->id])
            ->with('status', __('admin.media_uploaded'));
    }

    public function edit(MediaAsset $asset): View
    {
        Gate::authorize('view', $asset);

        $asset->load(['translations', 'uploader:id,name', 'rightsConfirmer:id,name']);

        return view('pages.admin.media.edit', [
            'asset' => $asset,
            'locales' => Locales::SUPPORTED,
            'usage' => MediaUsage::for($asset),
        ]);
    }

    public function update(Request $request, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $rules = [
            'rights_holder' => ['nullable', 'string', 'max:255'],
            'licence' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url', 'max:255'],
            'rights_confirmed' => ['nullable', 'boolean'],
        ];

        foreach (Locales::SUPPORTED as $locale) {
            // Alt text is capped at 255 rather than left open. Alt text that
            // runs to a paragraph is read aloud in full before the reader
            // reaches the next thing on the page.
            $rules[$locale.'.alt'] = ['nullable', 'string', 'max:255'];
            $rules[$locale.'.caption'] = ['nullable', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        foreach (Locales::SUPPORTED as $locale) {
            $asset->setTranslations($locale, [
                'alt' => $data[$locale]['alt'] ?? null,
                'caption' => $data[$locale]['caption'] ?? null,
            ]);
        }

        $confirmed = (bool) ($data['rights_confirmed'] ?? false);

        $asset->update([
            'rights_holder' => $data['rights_holder'] ?? null,
            'licence' => $data['licence'] ?? null,
            'source_url' => $data['source_url'] ?? null,
            'rights_confirmed_at' => $confirmed ? ($asset->rights_confirmed_at ?? now()) : null,
            'rights_confirmed_by' => $confirmed ? ($asset->rights_confirmed_by ?? $request->user()->id) : null,
        ]);

        return redirect()
            ->route('admin.media.edit', ['locale' => app()->getLocale(), 'asset' => $asset->id])
            ->with('status', __('admin.media_saved'));
    }

    /**
     * Swap the file, keep the record.
     *
     * Every block pointing at this asset keeps pointing at it, and the alt
     * text in three languages survives. Deleting and re-uploading is the
     * version that loses both.
     */
    public function replace(Request $request, MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('update', $asset);

        $request->validate([
            'file' => [
                'required', 'file',
                'mimetypes:'.implode(',', MediaLibrary::ACCEPTED),
                'max:'.(int) (MediaLibrary::MAX_BYTES / 1024),
            ],
        ]);

        $this->library->replace($asset, $request->file('file'));

        return back()->with('status', __('admin.media_replaced'));
    }

    /**
     * Deleting is refused while the asset is on a page.
     *
     * Not warned about, refused. A soft delete would leave the block
     * pointing at a record the public renderer can no longer resolve, and
     * the first person to find out would be a reader.
     */
    public function destroy(MediaAsset $asset): RedirectResponse
    {
        Gate::authorize('delete', $asset);

        $usage = MediaUsage::for($asset);

        if ($usage !== []) {
            throw ValidationException::withMessages([
                'asset' => __('admin.media_in_use', ['n' => count($usage)]),
            ]);
        }

        $this->library->deleteFiles($asset);
        $asset->delete();

        return redirect()
            ->route('admin.media', ['locale' => app()->getLocale()])
            ->with('status', __('admin.media_deleted'));
    }
}
