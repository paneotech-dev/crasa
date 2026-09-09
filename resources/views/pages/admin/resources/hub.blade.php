@extends('layouts.admin')

@section('title', __('admin.section_data'))
@section('note', __('admin.data_note'))

@section('content')
    @foreach ($groups as $group => $rows)
        @continue($rows === [])
        <section class="a-panel">
            <div class="a-panel__head">
                <h2>{{ __('admin.group_'.$group) }}</h2>
                <span class="a-panel__meta">{{ __('admin.group_'.$group.'_note') }}</span>
            </div>

            <div class="a-rows">
                @foreach ($rows as $row)
                    <div class="a-row">
                        <span class="a-row__grow">
                            <a href="{{ route('admin.resources', ['locale' => app()->getLocale(), 'resource' => $row['definition']->key]) }}">{{ $row['definition']->label() }}</a>
                            <span class="a-crow__where" style="display:block">{{ __('admin.resourcenote_'.$row['definition']->key) }}</span>
                        </span>
                        <span class="a-row__n">{{ number_format($row['count']) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
