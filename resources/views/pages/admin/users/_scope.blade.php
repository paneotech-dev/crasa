{{-- Item 1j: the member states and committees this moderator decides for. --}}
@php
    $scopeCountries = array_map('intval', (array) old('scope_countries', $moderationScope['countries']));
    $scopeCommittees = array_map('intval', (array) old('scope_committees', $moderationScope['committees']));
@endphp
<section class="a-panel" aria-labelledby="scope-heading">
    <div class="a-panel__head"><h2 id="scope-heading">{{ __('m3_scope.section') }}</h2></div>
    <p class="a-hint" id="scope-help">{{ __('m3_scope.help') }}</p>
    <p class="a-hint">{{ __('m3_scope.rule') }}</p>
    <fieldset @class(['a-fieldset', 'aw-field--error' => $errors->has('scope_countries*')]) aria-describedby="scope-help">
        <legend class="a-label">{{ __('m3_scope.member_states') }}</legend>
        @foreach ($countries as $country)
            <label class="a-check">
                <input type="checkbox" name="scope_countries[]" value="{{ $country->id }}" @checked(in_array($country->id, $scopeCountries, true))>
                <span class="a-check__label">{{ $country->displayName() }}</span>
            </label>
        @endforeach
    </fieldset>
    <fieldset @class(['a-fieldset', 'aw-field--error' => $errors->has('scope_committees*')]) aria-describedby="scope-help">
        <legend class="a-label">{{ __('m3_scope.committees') }}</legend>
        @forelse ($committees as $committee)
            <label class="a-check">
                <input type="checkbox" name="scope_committees[]" value="{{ $committee->id }}" @checked(in_array($committee->id, $scopeCommittees, true))>
                <span class="a-check__label">{{ $committee->displayName() }}</span>
            </label>
        @empty
            <p>{{ __('d3_people.no_committees') }}</p>
        @endforelse
    </fieldset>
</section>
