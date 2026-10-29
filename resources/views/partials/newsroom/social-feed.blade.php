{{--
    Newsroom: every published social post, newest first, then CRASA's
    three channels. Placed at the bottom of the newsroom with
    @includeIf('partials.newsroom.social-feed').
--}}
@php
    $socialPosts = \Illuminate\Support\Facades\Schema::hasTable('social_posts')
        ? \App\Models\SocialPost::live()->limit(12)->get()
        : collect();
@endphp
@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/social.css') }}">
    @endpush
@endonce
<section class="u-section u-section--pale s-feed" id="social" aria-labelledby="news-social-heading">
    <div class="u-container">
        <div class="s-latest__head">
            <h2 class="u-h2" id="news-social-heading">{{ __('fb.feed_heading') }}</h2>
            <p class="u-lead">{{ $socialPosts->isNotEmpty() ? __('fb.feed_intro') : __('fb.channels_intro') }}</p>
        </div>
        @if ($socialPosts->isNotEmpty())
            <div class="s-posts">
                @foreach ($socialPosts as $post)
                    @include('partials.social-post', ['post' => $post])
                @endforeach
            </div>
        @endif
        @include('partials.social-channel-cards')
    </div>
</section>
