@extends($layout)

{{--
    A folder: its name, description, place in the tree, clearance and who
    may see it, upload into it and validate what is in it.

    The same form in the back office and the member area. The access rules
    are three questions with the same answers, each one audience; a
    subfolder answers "as the folder above" until somebody changes it.
--}}

@php($isNew = ! $folder->exists)
@php($names = $surface === 'admin'
    ? ['store' => 'admin.documents.store', 'update' => 'admin.documents.update']
    : ['store' => 'extranet.folders.store', 'update' => 'extranet.folders.update'])

@section('title', $isNew ? __('d3_docs.new_folder') : __('d3_docs.edit_folder'))
@section('note', $isNew ? __('d3_docs.form_note') : $folder->name)

@section('crumbs')
    @if ($surface === 'admin')
        <li><a href="{{ route('admin.documents', ['locale' => app()->getLocale()]) }}">{{ __('d3_docs.nav_documents') }}</a></li>
    @else
        <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
        @if (! $isNew)
            <li><a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">{{ $folder->name }}</a></li>
        @endif
    @endif
    <li aria-current="page">{{ $isNew ? __('d3_docs.new_folder') : __('d3_docs.edit_folder') }}</li>
@endsection

@section('content')
    <form class="a-form a-form--card" method="POST"
          action="{{ $isNew ? route($names['store'], ['locale' => app()->getLocale()]) : route($names['update'], ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">
        @csrf
        @unless ($isNew)
            @method('PUT')
        @endunless

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('d3_docs.form_folder') }}</legend>

            <div class="a-field">
                <label class="a-label" for="name">{{ __('d3_docs.field_name') }} <span class="a-required">{{ __('admin.required') }}</span></label>
                <input class="a-input" type="text" id="name" name="name" maxlength="160" required value="{{ old('name', $folder->name) }}">
                @error('name')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="description">{{ __('d3_docs.field_description') }}</label>
                <textarea class="a-input" id="description" name="description" rows="3" maxlength="2000" aria-describedby="description-hint">{{ old('description', $folder->description) }}</textarea>
                <p class="a-hint" id="description-hint">{{ __('d3_docs.field_description_hint') }}</p>
                @error('description')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="parent_id">{{ __('d3_docs.field_parent') }}</label>
                <select class="a-select" id="parent_id" name="parent_id" aria-describedby="parent-hint">
                    <option value="">{{ __('d3_docs.parent_none') }}</option>
                    @foreach ($parents as $row)
                        <option value="{{ $row['folder']->id }}" @selected((string) old('parent_id', $folder->parent_id) === (string) $row['folder']->id)>{{ str_repeat('- ', $row['depth'] - 1) }}{{ $row['folder']->name }}</option>
                    @endforeach
                </select>
                <p class="a-hint" id="parent-hint">{{ __('d3_docs.field_parent_hint', ['max' => \App\Models\Folder::MAX_DEPTH]) }}</p>
                @error('parent_id')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>

            <div class="a-field">
                <label class="a-label" for="clearance">{{ __('d3_docs.field_clearance') }}</label>
                <select class="a-select" id="clearance" name="clearance" aria-describedby="clearance-hint">
                    @foreach ([\App\Enums\Clearance::Member, \App\Enums\Clearance::Confidential, \App\Enums\Clearance::Secretariat] as $clearance)
                        <option value="{{ $clearance->value }}" @selected(old('clearance', $folder->clearance?->value) === $clearance->value)>{{ $clearance->label() }}</option>
                    @endforeach
                </select>
                <p class="a-hint" id="clearance-hint">{{ __('d3_docs.field_clearance_hint') }}</p>
                @error('clearance')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        <fieldset class="a-fieldset">
            <legend class="a-legend">{{ __('d3_docs.access_title') }}</legend>
            <p class="a-hint">{{ __('d3_docs.access_hint') }}</p>

            <label class="a-check">
                <input type="hidden" name="inherit" value="0">
                <input type="checkbox" name="inherit" value="1" @checked(old('inherit', $inherits ? '1' : '0') === '1')>
                <span class="a-check__text">
                    <span class="a-check__label">{{ __('d3_docs.inherit_label') }}</span>
                    <span class="a-hint">{{ __('d3_docs.inherit_hint') }}</span>
                </span>
            </label>

            @foreach (\App\Support\Documents\FolderAccess::ABILITIES as $ability)
                @php($rule = old('access.'.$ability) ? \App\Support\Documents\FolderAccess::clean(old('access.'.$ability)) : $rules[$ability])
                <fieldset class="a-fieldset d3d-rule">
                    <legend class="a-label">{{ __('d3_docs.access_'.$ability) }}</legend>
                    <div class="d3d-rule__options">
                        @foreach (\App\Support\Documents\FolderAccess::TYPES as $type)
                            @continue($ability === 'view' && $type === 'secretariat')
                            <label class="a-check">
                                <input type="radio" name="access[{{ $ability }}][type]" value="{{ $type }}" @checked($rule['type'] === $type)>
                                <span class="a-check__label">{{ __('d3_docs.type_'.$type) }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('access.'.$ability.'.type')<p class="a-hint" role="alert">{{ $message }}</p>@enderror

                    <div class="a-field">
                        <span class="a-label" id="{{ $ability }}-roles-label">{{ __('d3_docs.field_roles') }}</span>
                        <div class="d3d-rule__options" role="group" aria-labelledby="{{ $ability }}-roles-label">
                            @foreach ($roles as $role)
                                <label class="a-check">
                                    <input type="checkbox" name="access[{{ $ability }}][roles][]" value="{{ $role->value }}" @checked(in_array($role->value, $rule['roles'], true))>
                                    <span class="a-check__label">{{ $role->label() }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('access.'.$ability.'.roles')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="a-field">
                        <label class="a-label" for="{{ $ability }}-country">{{ __('d3_docs.field_country') }}</label>
                        <select class="a-select" id="{{ $ability }}-country" name="access[{{ $ability }}][country_id]">
                            <option value="">{{ __('d3_docs.choose') }}</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected($rule['country_id'] === $country->id)>{{ $country->name }}</option>
                            @endforeach
                        </select>
                        @error('access.'.$ability.'.country_id')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="a-field">
                        <label class="a-label" for="{{ $ability }}-committee">{{ __('d3_docs.field_committee') }}</label>
                        <select class="a-select" id="{{ $ability }}-committee" name="access[{{ $ability }}][committee_id]">
                            <option value="">{{ __('d3_docs.choose') }}</option>
                            @foreach ($committees as $committee)
                                <option value="{{ $committee->id }}" @selected($rule['committee_id'] === $committee->id)>{{ $committee->name }}</option>
                            @endforeach
                        </select>
                        @error('access.'.$ability.'.committee_id')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>

                    <div class="a-field">
                        <label class="a-label" for="{{ $ability }}-users">{{ __('d3_docs.field_users') }}</label>
                        <select class="a-select" id="{{ $ability }}-users" name="access[{{ $ability }}][user_ids][]" multiple size="5" aria-describedby="{{ $ability }}-users-hint">
                            @foreach ($users as $person)
                                <option value="{{ $person->id }}" @selected(in_array($person->id, $rule['user_ids'], true))>{{ $person->name }} ({{ $person->email }})</option>
                            @endforeach
                        </select>
                        <p class="a-hint" id="{{ $ability }}-users-hint">{{ __('d3_docs.field_users_hint') }}</p>
                        @error('access.'.$ability.'.user_ids')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>
                </fieldset>
            @endforeach

            <p class="a-hint">{{ __('d3_docs.access_secretariat_note') }}</p>
        </fieldset>

        <div class="a-form__actions">
            <button class="a-btn a-btn--primary" type="submit">{{ $isNew ? __('d3_docs.create_action') : __('d3_docs.save_action') }}</button>
        </div>
    </form>
@endsection
