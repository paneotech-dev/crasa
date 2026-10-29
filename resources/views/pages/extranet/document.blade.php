@extends('layouts.extranet')

{{--
    One file in a folder: what it is, its versions with who and when, its
    share link, and what the reader may do with it. Every link here goes
    through a controller that checks access again; the share link too.
--}}

@section('title', $document->original_filename)
@section('note', $folder->name)

@section('crumbs')
    <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    @foreach ($ancestors as $ancestor)
        <li><a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $ancestor->id]) }}">{{ $ancestor->name }}</a></li>
    @endforeach
    <li><a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">{{ $folder->name }}</a></li>
    <li aria-current="page">{{ $document->original_filename }}</li>
@endsection

@section('actions')
    @if ($document->isPdf())
        <a class="a-btn a-btn--secondary" href="{{ route('documents.show', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ __('d3_docs.open_pdf') }}</a>
    @endif
    <a class="a-btn a-btn--primary" href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ __('extranet.download') }}</a>
@endsection

@section('content')
    <section class="a-panel">
        <div class="a-panel__head">
            <h2>{{ __('d3_docs.about_file') }}</h2>
            <span class="a-panel__meta">
                <span class="a-pill" data-status="{{ $document->clearance->value }}">{{ $document->clearance->label() }}</span>
                @if ($document->validated_at)
                    <span class="a-pill" data-status="validated">{{ __('d3_docs.status_validated') }}</span>
                @endif
            </span>
        </div>
        <div class="a-rows">
            <div class="a-row"><span class="a-row__grow">{{ __('d3_docs.meta_version', ['n' => $document->version]) }}</span></div>
            <div class="a-row"><span class="a-row__grow">{{ __('d3_docs.meta_added', ['who' => $document->uploader?->name ?? __('admin.system'), 'when' => $document->updated_at?->displayed()->isoFormat('D MMMM YYYY, HH:mm')]) }}</span></div>
            <div class="a-row"><span class="a-row__grow">{{ __('d3_docs.meta_size', ['size' => $document->readableSize()]) }}</span></div>
            @if ($document->validated_at)
                <div class="a-row"><span class="a-row__grow">{{ __('d3_docs.meta_validated', ['who' => $document->validator?->name ?? __('admin.system'), 'when' => $document->validated_at->displayed()->isoFormat('D MMMM YYYY')]) }}</span></div>
            @endif
        </div>

        @if ($canValidate && ! $document->validated_at)
            <form class="d3d-panel-note" method="POST" action="{{ route('extranet.document.validate', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                @csrf @method('PUT')
                <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.validate_action') }}</button>
            </form>
        @endif
    </section>

    <section class="a-panel">
        <div class="a-panel__head"><h2>{{ __('d3_docs.share_title') }}</h2></div>
        <div class="d3d-share">
            <label class="c-sr" for="share-link">{{ __('d3_docs.share_title') }}</label>
            <input class="a-input" type="text" id="share-link" value="{{ $shareUrl }}" readonly aria-describedby="share-hint">
        </div>
        <p class="a-hint d3d-panel-note" id="share-hint">{{ __('d3_docs.share_hint') }}</p>
    </section>

    <section class="a-resource">
        <div class="a-resource__head"><h2 class="a-resource__title">{{ __('d3_docs.versions_title') }}</h2></div>
        <div class="a-tablewrap">
            <table class="a-table">
                <caption class="c-sr">{{ __('d3_docs.versions_title') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('d3_docs.col_version') }}</th>
                        <th scope="col">{{ __('extranet.col_file') }}</th>
                        <th scope="col">{{ __('extranet.col_owner') }}</th>
                        <th scope="col" data-quiet>{{ __('extranet.col_uploaded') }}</th>
                        <th scope="col" data-numeric>{{ __('extranet.col_size') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($document->versions as $version)
                        <tr>
                            <th scope="row">{{ $version->version }}@if ($version->version === $document->version) <span class="d3d-version">{{ __('d3_docs.current') }}</span>@endif</th>
                            <td>{{ $version->original_filename }}</td>
                            <td>{{ $version->uploader?->name ?? __('admin.system') }}</td>
                            <td data-quiet>{{ $version->created_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td data-numeric>{{ number_format(max(1, $version->size_bytes / 1024)) }} KB</td>
                            <td><a href="{{ route('extranet.version', ['locale' => app()->getLocale(), 'version' => $version->id]) }}" aria-label="{{ __('d3_docs.download_version', ['n' => $version->version]) }}">{{ __('extranet.download') }}</a></td>
                        </tr>
                    @empty
                        <tr>
                            <th scope="row">{{ $document->version }}</th>
                            <td>{{ $document->original_filename }}</td>
                            <td>{{ $document->uploader?->name ?? __('admin.system') }}</td>
                            <td data-quiet>{{ $document->created_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</td>
                            <td data-numeric>{{ $document->readableSize() }}</td>
                            <td><a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ __('extranet.download') }}</a></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($canManage)
        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('d3_docs.manage_title') }}</h2></div>
            <div class="d3d-actions">
                <form class="a-form" method="POST" action="{{ route('extranet.document.rename', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                    @csrf @method('PUT')
                    <div class="a-field">
                        <label class="a-label" for="name">{{ __('d3_docs.rename_label') }}</label>
                        <input class="a-input" type="text" id="name" name="name" maxlength="200" required value="{{ old('name', $document->original_filename) }}">
                        @error('name')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.rename_action') }}</button>
                </form>

                <form class="a-form" method="POST" enctype="multipart/form-data" action="{{ route('extranet.document.replace', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                    @csrf
                    <div class="a-field">
                        <label class="a-label" for="file">{{ __('d3_docs.replace_label') }}</label>
                        <input class="a-input" type="file" id="file" name="file" required aria-describedby="replace-hint" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                        <p class="a-hint" id="replace-hint">{{ __('d3_docs.replace_hint') }}</p>
                        @error('file')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.replace_action') }}</button>
                </form>

                @if ($destinations->isNotEmpty())
                    <form class="a-form" method="POST" action="{{ route('extranet.document.move', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                        @csrf @method('PUT')
                        <div class="a-field">
                            <label class="a-label" for="folder_id">{{ __('d3_docs.move_label') }}</label>
                            <select class="a-select" id="folder_id" name="folder_id" required>
                                @foreach ($destinations as $destination)
                                    <option value="{{ $destination->id }}">{{ $destination->name }}{{ $destination->country_id ? ' ('.$destination->country?->name.')' : '' }}</option>
                                @endforeach
                            </select>
                            @error('folder_id')<p class="a-hint" role="alert">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.move_action') }}</button>
                    </form>
                @endif

                <form class="a-form" method="POST" action="{{ route('extranet.document.destroy', ['locale' => app()->getLocale(), 'document' => $document->id]) }}"
                      data-confirm="{{ __('d3_docs.bin_confirm', ['name' => $document->original_filename, 'days' => \App\Models\Document::BIN_DAYS]) }}" data-confirm-title="{{ __('d3_docs.bin_action') }}" data-confirm-action="{{ __('d3_docs.bin_action') }}">
                    @csrf @method('DELETE')
                    <p class="a-hint">{{ __('d3_docs.bin_hint', ['days' => \App\Models\Document::BIN_DAYS]) }}</p>
                    <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.bin_action') }}</button>
                </form>
            </div>
        </section>
    @endif
@endsection
