{{-- One labelled field: label above, required in words, helper text and an
     inline error tied to the input with aria-describedby. --}}
@php
    $id = $id ?? str_replace(['[', ']'], ['_', ''], $name);
    $type = $type ?? 'text';
    $value = old($name, $value ?? '');
    $error = $errors->first($name);
    $describedBy = trim(($help ?? false ? $id.'-help ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="a-field e-field @if ($error) e-field--error @endif" @if ($error) data-state="missing" @endif>
    <label class="a-label" for="{{ $id }}">{{ $label }} <span class="e-req">{{ ($required ?? false) ? __('e.required') : __('e.optional') }}</span></label>
    @if ($type === 'textarea')
        <textarea class="a-input" id="{{ $id }}" name="{{ $name }}" rows="{{ $rows ?? 4 }}" @if ($required ?? false) required @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif data-summary="{{ $label }}">{{ $value }}</textarea>
    @elseif ($type === 'select')
        <select class="a-input" id="{{ $id }}" name="{{ $name }}" @if ($required ?? false) required @endif @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif data-summary="{{ $label }}">
            @foreach ($options as $optValue => $optLabel)
                <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
            @endforeach
        </select>
    @else
        <input class="a-input" type="{{ $type }}" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @if ($required ?? false) required @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif @if ($error) aria-invalid="true" @endif
               @isset($max) maxlength="{{ $max }}" @endisset @isset($min) min="{{ $min }}" @endisset data-summary="{{ $label }}">
    @endif
    @if ($help ?? false)<p class="a-hint" id="{{ $id }}-help">{{ $help }}</p>@endif
    @if ($error)<p class="e-error" id="{{ $id }}-error">{{ $error }}</p>@endif
</div>
