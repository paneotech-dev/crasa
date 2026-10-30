@extends('layouts.admin')

@section('title', __('admin.translations_title'))
@section('note', __('admin.translations_note'))

@section('content')
    @include('pages.admin.translations._approved_drafts')

    <section class="a-resource">
        <div class="a-resource__head">
            <form class="a-filters" method="GET" action="{{ url()->current() }}">
                <label class="c-sr" for="type">{{ __('admin.col_type') }}</label>
                <select class="a-filter" id="type" name="type">
                    @foreach ($types as $option)
                        <option value="{{ $option }}" @selected($type === $option)>{{ __('admin.trtype_'.$option) }}</option>
                    @endforeach
                </select>
                <button type="submit" class="a-btn a-btn--secondary">{{ __('admin.apply') }}</button>
            </form>
        </div>

        @if ($rows === [])
            <div style="padding:var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('admin.translations_empty_title') }}</div>
                    <div class="a-empty__body">{{ __('admin.translations_empty_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.col_record') }}</th>
                            @foreach (\App\Support\Locales::SUPPORTED as $locale)
                                <th scope="col">{{ \App\Support\Locales::nameOf($locale) }}</th>
                            @endforeach
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.translations.edit', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $row['record']->getKey()]) }}">{{ $row['label'] }}</a>
                                </th>
                                @foreach (\App\Support\Locales::SUPPORTED as $locale)
                                    @php $counts = $row['coverage'][$locale]; @endphp
                                    <td>
                                        {{-- The sentence, not a percentage. A
                                             language that is entirely machine
                                             drafted must not read as done. --}}
                                        <span class="a-coverage" @if ($counts['ready']) data-ready @endif>
                                            <span class="a-coverage__track">
                                                <span class="a-coverage__fill" style="width:{{ \App\Support\TranslationCoverage::percentage($counts) }}%"></span>
                                            </span>
                                            <span class="a-coverage__note">{{ \App\Support\TranslationCoverage::describe($counts) }}</span>
                                        </span>
                                    </td>
                                @endforeach
                                <td>
                                    <span class="a-table__actions">
                                        <a href="{{ route('admin.translations.edit', ['locale' => app()->getLocale(), 'type' => $type, 'id' => $row['record']->getKey()]) }}">{{ __('admin.translate') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="a-pager">{{ $records->links() }}</div>
            @endif
        @endif
    </section>
@endsection
