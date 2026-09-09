@extends('layouts.admin')

@section('title', __('import.title'))
@section('note', __('import.note'))

@section('crumbs')
    <li><a href="{{ route('submissions', ['locale' => app()->getLocale()]) }}">{{ __('submissions.title') }}</a></li>
    <li><a href="{{ route('submissions.edit', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ $submission->period }}</a></li>
    <li aria-current="page">{{ __('import.title') }}</li>
@endsection

@section('content')
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('import.step_template') }}</h2>
            <span class="a-panel__meta">{{ __('import.step_template_note') }}</span>
        </div>
        <div class="a-form__actions">
            <a class="a-btn a-btn--secondary" href="{{ route('submissions.template', ['locale' => app()->getLocale()]) }}">{{ __('import.template_all') }}</a>
            @foreach ($categories as $category)
                <a class="a-btn a-btn--secondary" href="{{ route('submissions.template', ['locale' => app()->getLocale(), 'category' => $category]) }}">
                    {{ __('comparator.category_'.$category) }}
                </a>
            @endforeach
        </div>
        <p class="a-hint">{{ __('import.template_hint') }}</p>
    </section>

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('import.step_upload') }}</h2>
            <span class="a-panel__meta">{{ __('import.step_upload_note') }}</span>
        </div>
        <form class="a-form" method="POST" enctype="multipart/form-data"
              action="{{ route('submissions.import.inspect', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
            @csrf
            <div class="a-field">
                <label class="a-label" for="file">{{ __('import.file') }}</label>
                <input class="a-input" type="file" id="file" name="file" accept=".csv,text/csv" required>
                <span class="a-hint">{{ __('import.file_hint') }}</span>
            </div>
            <div class="a-form__actions">
                <button type="submit" class="a-btn a-btn--primary">{{ __('import.check_action') }}</button>
            </div>
        </form>
    </section>

    @if ($pending)
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('import.step_report') }}</h2>
                <span class="a-panel__meta">{{ $pending['name'] }}</span>
            </div>

            @if ($pending['errors'] !== [])
                {{-- Row, column, reason. An error that says "import failed"
                     makes somebody open the file and guess. --}}
                <div class="a-band a-band--error">
                    <strong>{{ trans_choice('import.errors_found', count($pending['errors']), ['count' => count($pending['errors'])]) }}</strong>
                    {{ __('import.errors_body') }}
                </div>

                <div class="a-tablewrap">
                    <table class="a-table" style="min-width:0">
                        <thead>
                            <tr>
                                <th scope="col" data-numeric>{{ __('import.col_row') }}</th>
                                <th scope="col">{{ __('import.col_column') }}</th>
                                <th scope="col">{{ __('import.col_indicator') }}</th>
                                <th scope="col">{{ __('import.col_reason') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending['errors'] as $error)
                                <tr>
                                    <th scope="row" data-numeric>{{ $error['line'] }}</th>
                                    <td data-quiet>{{ $error['column'] }}</td>
                                    <td>{{ $error['indicator'] ?? '' }}</td>
                                    <td>{{ $error['reason'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="a-notice a-notice--success" role="status">
                    {{ trans_choice('import.ready', count($pending['rows']), ['count' => count($pending['rows'])]) }}
                </div>

                <div class="a-tablewrap">
                    <table class="a-table" style="min-width:0">
                        <thead>
                            <tr>
                                <th scope="col" data-numeric>{{ __('import.col_row') }}</th>
                                <th scope="col">{{ __('import.col_indicator') }}</th>
                                <th scope="col" data-numeric>{{ __('import.col_value') }}</th>
                                <th scope="col">{{ __('import.col_source_note') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pending['rows'] as $row)
                                <tr>
                                    <th scope="row" data-numeric>{{ $row['line'] }}</th>
                                    <td>{{ $row['indicator'] }}</td>
                                    <td data-numeric>{{ $row['value'] }}</td>
                                    <td data-quiet>{{ $row['source_note'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <form class="a-form" method="POST"
                      action="{{ route('submissions.import.commit', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">
                    @csrf
                    <p class="a-hint">{{ __('import.commit_hint') }}</p>
                    <div class="a-form__actions">
                        <button type="submit" class="a-btn a-btn--primary">{{ __('import.commit_action') }}</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('import.step_export') }}</h2>
            <span class="a-panel__meta">{{ __('import.step_export_note') }}</span>
        </div>
        <div class="a-form__actions">
            <a class="a-btn a-btn--secondary" href="{{ route('submissions.export.csv', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ __('import.export_csv') }}</a>
            <a class="a-btn a-btn--secondary" href="{{ route('submissions.export.json', ['locale' => app()->getLocale(), 'submission' => $submission->id]) }}">{{ __('import.export_json') }}</a>
        </div>
    </section>
@endsection
