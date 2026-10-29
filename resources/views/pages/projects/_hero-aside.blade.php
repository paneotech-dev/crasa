{{-- The partners named on the projects, beside the hero. Read from the
     records; a project with no partner recorded adds nothing here. --}}
<div class="u-panel cp-proj-aside">
    <h2 class="cp-side-title">{{ __('cpages_a.partners_heading') }}</h2>
    <ul class="cp-proj-partners cp-proj-aside__partners" role="list">
        @foreach ($partners as $partner)<li>{{ $partner }}</li>@endforeach
    </ul>
    <p class="u-help cp-proj-aside__note">{{ __('cpages_a.partners_note') }}</p>
</div>
