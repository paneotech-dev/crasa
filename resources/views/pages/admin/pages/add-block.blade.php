@extends('layouts.admin')

@section('title', __('admin.add_block'))
@section('note', __('admin.add_block_note'))

@section('crumbs')
    <li><a href="{{ route('admin.pages', ['locale' => app()->getLocale()]) }}">{{ __('admin.section_content') }}</a></li>
    <li><a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ $page->slug }}</a></li>
    <li aria-current="page">{{ __('admin.add_block') }}</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.pages.blocks.store', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">
        @csrf

        <div class="a-grid a-grid--stats">
            @foreach ($types as $type)
                <label class="a-panel a-panel--tight" style="cursor:pointer;display:block">
                    <span style="display:flex;gap:9px;align-items:flex-start">
                        <input type="radio" name="block_type" value="{{ $type->value }}" @checked($loop->first)>
                        <span>
                            <span class="a-stat__label" style="margin-top:0;display:block">{{ $type->label() }}</span>
                            {{-- Plain language, not the enum name. An editor
                                 choosing a block should not have to know what
                                 a featured_comparison is. --}}
                            <span class="a-stat__note">{{ __('blocks.explain_'.$type->value) }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>

        <div class="a-form__actions" style="margin-top:var(--s-5)">
            <button type="submit" class="a-btn a-btn--primary">{{ __('admin.add_block_submit') }}</button>
            <a href="{{ route('admin.pages.edit', ['locale' => app()->getLocale(), 'page' => $page->slug]) }}">{{ __('admin.cancel') }}</a>
        </div>
    </form>
@endsection
