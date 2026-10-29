@extends('layouts.extranet')

@section('title', $folder->name)
@section('note', $folder->country?->name ?? $folder->committee?->name ?? __('extranet.regional'))

@section('crumbs')
    <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    @foreach ($ancestors as $ancestor)
        <li><a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $ancestor->id]) }}">{{ $ancestor->name }}</a></li>
    @endforeach
    <li aria-current="page">{{ $folder->name }}</li>
@endsection

@if ($canEdit || $canAddSubfolder || $canUpload)
    @section('actions')
        @if ($canAddSubfolder)
            <a class="a-btn a-btn--secondary" href="{{ route('extranet.folders.create', ['locale' => app()->getLocale(), 'parent' => $folder->id]) }}">{{ __('d3_docs.new_subfolder') }}</a>
        @endif
        @if ($canEdit)
            <a class="a-btn a-btn--secondary" href="{{ route('extranet.folders.edit', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">{{ __('d3_docs.edit_folder') }}</a>
        @endif
        @if ($canUpload)
            <a class="a-btn a-btn--secondary" href="{{ route('extranet.documents.bin', ['locale' => app()->getLocale()]) }}">{{ __('d3_docs.bin_title') }}</a>
        @endif
    @endsection
@endif

@section('content')
    <section class="a-resource">
        <div class="a-resource__head">
            <div class="a-resource__bar">
                <div>
                    <h2 class="a-resource__title">{{ $folder->name }}</h2>
                    <div class="a-resource__note">
                        <span class="a-pill" data-status="{{ $folder->clearance->value }}">{{ $folder->clearance->label() }}</span>
                        {{ trans_choice('extranet.file_count', $documents->count(), ['count' => $documents->count()]) }}, {{ $folder->humanSize() }}
                    </div>
                    @if ($folder->description)
                        <p class="d3d-description">{{ $folder->description }}</p>
                    @endif
                </div>
            </div>
        </div>

        @if ($children->isNotEmpty())
            <div class="e-tree">
                @foreach ($children as $child)
                    <a class="e-tree__row" href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $child->id]) }}">
                        <span class="e-tree__icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 7a1 1 0 011-1h5l2 2h9a1 1 0 011 1v9a1 1 0 01-1 1H4a1 1 0 01-1-1z"/></svg>
                        </span>
                        <span class="e-tree__label">{{ $child->name }}</span>
                        <span class="e-tree__meta"><span class="a-pill" data-status="{{ $child->clearance->value }}">{{ $child->clearance->label() }}</span></span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($canUpload)
            {{-- Screen 12's upload panel, several files at once. Archives
                 are refused: a zip is a way past a file-type check. --}}
            <form class="e-upload" method="POST" enctype="multipart/form-data"
                  action="{{ route('extranet.upload', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">
                @csrf
                <label class="a-label" for="files">{{ __('d3_docs.upload_label') }}</label>
                <input class="a-input" type="file" id="files" name="files[]" multiple required aria-describedby="files-hint"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                <p class="a-hint" id="files-hint">{{ __('extranet.upload_hint') }}</p>
                <button type="submit" class="a-btn a-btn--primary">{{ __('d3_docs.upload_action') }}</button>
            </form>
        @endif

        @if ($documents->isEmpty())
            <div style="padding:var(--s-5)">
                <div class="a-empty">
                    <div class="a-empty__title">{{ __('extranet.no_files_title') }}</div>
                    <div class="a-empty__body">{{ $canUpload ? __('extranet.no_files_body_upload') : __('extranet.no_files_body') }}</div>
                </div>
            </div>
        @else
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('extranet.col_file') }}</th>
                            <th scope="col">{{ __('extranet.col_owner') }}</th>
                            <th scope="col" data-quiet>{{ __('extranet.col_uploaded') }}</th>
                            <th scope="col" data-numeric>{{ __('extranet.col_size') }}</th>
                            <th scope="col">{{ __('d3_docs.col_status') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <th scope="row">
                                    <span class="e-file">
                                        <span class="e-file__format" aria-hidden="true">{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }}</span>
                                        <a href="{{ route('extranet.document', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ $document->original_filename }}</a>
                                    </span>
                                </th>
                                <td>{{ $document->uploader?->name ?? __('admin.system') }}</td>
                                <td data-quiet>{{ $document->created_at->displayed()->isoFormat('D MMM YYYY') }}</td>
                                <td data-numeric>{{ $document->readableSize() }}</td>
                                <td>
                                    @if ($document->validated_at)
                                        <span class="a-pill" data-status="validated">{{ __('d3_docs.status_validated') }}</span>
                                    @endif
                                    @if ($document->version > 1)
                                        <span class="d3d-version">{{ __('d3_docs.version_n', ['n' => $document->version]) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="a-table__actions">
                                        {{-- Never a filesystem path. Every byte
                                             goes through the controller, which
                                             checks clearance, country and
                                             committee before streaming. --}}
                                        @if ($document->isPdf())
                                            <a href="{{ route('documents.show', ['locale' => app()->getLocale(), 'document' => $document->id]) }}" aria-label="{{ __('d3_docs.open_named', ['name' => $document->original_filename]) }}">{{ __('d3_docs.open') }}</a>
                                        @endif
                                        <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}" aria-label="{{ __('d3_docs.download_named', ['name' => $document->original_filename]) }}">{{ __('extranet.download') }}</a>
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Screen 12's "Who can see this folder" panel. Stated, not inferred. --}}
    <section class="a-panel">
        <div class="a-panel__head"><h2>{{ __('extranet.who_can_see') }}</h2></div>
        <div class="a-rows">
            @foreach ($access as $line)
                <div class="a-row">
                    <span class="a-row__grow">{{ $line }}</span>
                </div>
            @endforeach
        </div>
        @if ($inheritedFrom)
            <p class="a-hint d3d-panel-note">{{ __('d3_docs.inherited_from', ['folder' => $inheritedFrom->name]) }}</p>
        @endif
    </section>

    <section class="a-panel">
        <div class="a-panel__head"><h2>{{ __('d3_docs.activity_title') }}</h2></div>
        @if ($activity->isEmpty())
            <p class="a-hint d3d-panel-note">{{ __('d3_docs.activity_empty') }}</p>
        @else
            <div class="a-rows">
                @foreach ($activity as $row)
                    <div class="a-row">
                        <span class="a-row__grow">{{ __('d3_docs.activity_'.$row->action, ['who' => $row->user?->name ?? __('admin.system'), 'name' => $row->changes['filename'] ?? $row->changes['name'] ?? '', 'version' => $row->changes['version'] ?? '']) }}</span>
                        <span class="d3d-when">{{ $row->created_at?->displayed()->isoFormat('D MMM YYYY, HH:mm') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($canDelete)
        <section class="a-panel">
            <div class="a-panel__head"><h2>{{ __('d3_docs.delete_folder') }}</h2></div>
            <p class="a-hint d3d-panel-note">{{ __('d3_docs.delete_folder_hint') }}</p>
            <form class="d3d-panel-note" method="POST" action="{{ route('extranet.folders.destroy', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}"
                  data-confirm="{{ __('d3_docs.delete_folder_confirm', ['name' => $folder->name]) }}" data-confirm-title="{{ __('d3_docs.delete_folder') }}" data-confirm-action="{{ __('d3_docs.delete_folder') }}">
                @csrf @method('DELETE')
                <button type="submit" class="a-btn a-btn--secondary">{{ __('d3_docs.delete_folder') }}</button>
            </form>
        </section>
    @endif
@endsection
