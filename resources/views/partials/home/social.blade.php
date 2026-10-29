{{--
    Home: "Latest from CRASA". The three most recent published social
    posts in one row. With none published, CRASA's three channels in one
    slim row and nothing else.
--}}
@php
    $socialPosts = \Illuminate\Support\Facades\Schema::hasTable('social_posts')
        ? \App\Models\SocialPost::live()->limit(3)->get()
        : collect();
@endphp
@once
    @push('styles')
        <link rel="stylesheet" href="{{ \App\Support\Assets::url('css/social.css') }}">
    @endpush
@endonce
<section class="u-section s-latest" id="latest-from-crasa" aria-labelledby="latest-from-crasa-heading">
    <div class="u-container">
        @if ($socialPosts->isNotEmpty())
            <div class="s-latest__head">
                <h2 class="u-h2" id="latest-from-crasa-heading">{{ __('fb.latest_heading') }}</h2>
                <p class="u-lead">{{ __('fb.latest_intro') }}</p>
            </div>
            <div class="s-posts s-posts--row">
                @foreach ($socialPosts as $post)
                    @include('partials.social-post', ['post' => $post])
                @endforeach
            </div>
        @else
            <div class="s-latest__head s-latest__head--slim">
                <h2 class="u-h3" id="latest-from-crasa-heading">{{ __('fb.channels_heading') }}</h2>
            </div>
            @include('partials.social-channel-cards')
        @endif
    </div>
</section>
