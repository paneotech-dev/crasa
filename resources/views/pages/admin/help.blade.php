@extends('layouts.admin')

@section('title', __('help.title'))
@section('note', __('help.note'))

@section('actions')
    <form method="POST" action="{{ route('admin.help.reset', ['locale' => app()->getLocale()]) }}">
        @csrf
        <button type="submit" class="a-btn a-btn--secondary">{{ __('help.restore_hints') }}</button>
    </form>
@endsection

@section('content')
    <section class="a-panel a-intro">
        <div class="a-panel__head">
            <h2>{{ __('help.sections_title') }}</h2>
        </div>
        <ol>
            @foreach ($steps as $step)
                <li>
                    <span class="a-intro__text">
                        <b><a href="{{ $step['url'] }}">{{ $step['title'] }}</a></b>
                        <span>{{ $step['body'] }}</span>
                    </span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('help.blocks_title') }}</h2>
            <span class="a-panel__meta">{{ __('help.blocks_note') }}</span>
        </div>
        <div class="a-rows">
            @foreach (\App\Enums\BlockType::cases() as $blockType)
                <div class="a-row">
                    <span class="a-row__time" style="width:170px;font-weight:600;color:var(--a-ink)">{{ $blockType->label() }}</span>
                    <span class="a-row__grow">{{ __('blocks.explain_'.$blockType->value) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('help.rules_title') }}</h2>
        </div>
        <div class="a-rows">
            @foreach (['languages', 'publishing', 'alt_text', 'deleting', 'audit', 'placeholders'] as $rule)
                <div class="a-row" style="align-items:flex-start;flex-direction:column;gap:4px">
                    <span class="a-crow__field">{{ __('help.rule_'.$rule.'_title') }}</span>
                    <span class="a-row__grow" style="max-width:78ch">{{ __('help.rule_'.$rule.'_body') }}</span>
                </div>
            @endforeach
        </div>
    </section>
@endsection
