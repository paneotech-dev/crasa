<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use App\Support\SocialEmbed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Social posts: the editor pastes the address of one of CRASA's posts on X,
 * Facebook or LinkedIn. It is saved as a draft and goes on the site when
 * somebody who may publish content publishes it.
 */
class SocialPostAdminController extends Controller
{
    public function index(): View
    {
        Gate::authorize(Permission::ManageContent->value);

        return view('pages.admin.social.index', [
            'posts' => SocialPost::orderByDesc('posted_at')->orderByDesc('id')->paginate(25),
        ]);
    }

    public function create(): View
    {
        Gate::authorize(Permission::ManageContent->value);

        return view('pages.admin.social.form', ['post' => new SocialPost]);
    }

    public function edit(SocialPost $post): View
    {
        Gate::authorize(Permission::ManageContent->value);

        return view('pages.admin.social.form', ['post' => $post]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $data = $this->validated($request);
        $resolved = SocialEmbed::resolve($data['url']);

        $post = SocialPost::create([
            'network' => $resolved['network'],
            'url' => $data['url'],
            'posted_at' => $data['posted_at'] ?? SocialEmbed::dateFromId($data['url']) ?? now(),
            'caption' => $data['caption'] ?? null,
            'embed_html' => $resolved['embed_html'],
            'embed_url' => $resolved['embed_url'],
            'status' => 'draft',
            'author_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.social.edit', ['locale' => app()->getLocale(), 'post' => $post->id])
            ->with('status', $this->savedMessage($post));
    }

    public function update(Request $request, SocialPost $post): RedirectResponse
    {
        \App\Support\Approvals::refuseIfLocked($post);

        Gate::authorize('update', $post);

        $data = $this->validated($request, $post);
        $changes = [
            'posted_at' => $data['posted_at'] ?? $post->posted_at,
            'caption' => $data['caption'] ?? null,
        ];

        // A new address, or an X post whose embed never came back, is
        // fetched again. Otherwise the stored markup is kept.
        if ($data['url'] !== $post->url || ($post->network === 'x' && blank($post->embed_html))) {
            $changes += ['url' => $data['url']] + SocialEmbed::resolve($data['url']);
        }

        $post->update($changes);

        return back()->with('status', $this->savedMessage($post));
    }

    /**
     * Publishing directly. Where the rule for social posts is "one
     * approval" (the default) only an administrator may; an editor sends the
     * post for approval from the panel instead.
     */
    public function publish(Request $request, SocialPost $post): RedirectResponse
    {
        Gate::authorize('publish', $post);

        $post->update(['status' => 'published', 'published_at' => now()]);
        \App\Support\Approvals::recordDirectPublish($post, $request->user());

        return back()->with('status', __('fb.social_published'));
    }

    public function unpublish(SocialPost $post): RedirectResponse
    {
        Gate::authorize(Permission::PublishContent->value);

        $post->update(['status' => 'draft']);

        return back()->with('status', __('fb.social_unpublished'));
    }

    public function destroy(SocialPost $post): RedirectResponse
    {
        Gate::authorize(Permission::ManageContent->value);

        $post->delete();

        return redirect()
            ->route('admin.social', ['locale' => app()->getLocale()])
            ->with('status', __('fb.social_deleted'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SocialPost $post = null): array
    {
        $request->merge(['url' => trim((string) $request->input('url'))]);

        return $request->validate([
            'url' => [
                'required', 'url:https', 'max:500',
                Rule::unique('social_posts', 'url')->ignore($post?->id),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (SocialEmbed::network((string) $value) === null) {
                        $fail(__('fb.social_error_network'));
                    } elseif (! SocialEmbed::isPost((string) $value)) {
                        $fail(__('fb.social_error_post'));
                    }
                },
            ],
            'posted_at' => ['nullable', 'date'],
            'caption' => ['nullable', 'string', 'max:500'],
        ], [
            'url.required' => __('fb.social_error_required'),
            'url.url' => __('fb.social_error_url'),
            'url.unique' => __('fb.social_error_unique'),
        ]);
    }

    private function savedMessage(SocialPost $post): string
    {
        if ($post->network === 'x' && blank($post->embed_html)) {
            return __('fb.social_saved_no_embed');
        }

        return __('fb.social_saved');
    }
}
