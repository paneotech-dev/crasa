{{-- One person's fields, shared by the add form and each edit form, so the
     consent control cannot exist on one and not the other. --}}
@php($id = $contact?->id ?? 'new')

<div class="a-field">
    <label class="a-label" for="name-{{ $id }}">{{ __('directory.field_name') }}<span class="a-required">{{ __('admin.required') }}</span></label>
    <input class="a-input" type="text" id="name-{{ $id }}" name="name" maxlength="160" required value="{{ old('name', $contact?->name) }}">
</div>

<div class="a-field">
    <label class="a-label" for="role-{{ $id }}">{{ __('directory.field_role') }}</label>
    <input class="a-input" type="text" id="role-{{ $id }}" name="role" maxlength="160" value="{{ old('role', $contact?->role) }}">
</div>

<div class="a-field">
    <label class="a-label" for="email-{{ $id }}">{{ __('directory.field_email') }}</label>
    <input class="a-input" type="email" id="email-{{ $id }}" name="email" maxlength="160" value="{{ old('email', $contact?->email) }}">
</div>

<div class="a-field">
    <label class="a-label" for="phone-{{ $id }}">{{ __('directory.field_phone') }}</label>
    <input class="a-input" type="text" id="phone-{{ $id }}" name="phone" maxlength="60" value="{{ old('phone', $contact?->phone) }}">
</div>

<div class="a-field">
    <span class="a-label">{{ __('directory.field_sectors') }}</span>
    @foreach ($sectors as $sector)
        <div class="a-check">
            <input type="checkbox" id="sector-{{ $id }}-{{ $sector->id }}" name="sectors[]" value="{{ $sector->id }}"
                   @checked($contact?->sectors->contains($sector->id))>
            <span class="a-check__text">
                <label class="a-check__label" for="sector-{{ $id }}-{{ $sector->id }}">{{ $sector->name }}</label>
            </span>
        </div>
    @endforeach
</div>

{{-- The consent control. A row appears in the directory because this is
     ticked, and comes out of it the moment it is not. --}}
<div class="a-check">
    <input type="checkbox" id="consent-{{ $id }}" name="consented" value="1" @checked($contact?->isListed())>
    <span class="a-check__text">
        <label class="a-check__label" for="consent-{{ $id }}">{{ __('directory.consent') }}</label>
        <span class="a-hint">{{ __('directory.consent_hint') }}</span>
    </span>
</div>
