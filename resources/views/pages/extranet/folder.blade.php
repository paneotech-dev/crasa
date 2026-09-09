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
            {{-- Screen 12's upload panel. Archives are refused: a zip is a
                 way past a MIME check, and a folder whose contents nobody
                 can enumerate is not a document store. --}}
            <form class="e-upload" method="POST" enctype="multipart/form-data"
                  action="{{ route('extranet.upload', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">
                @csrf
                <label class="a-label" for="file">{{ __('extranet.upload_label') }}</label>
                <input class="a-input" type="file" id="file" name="file" required
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
                <p class="a-hint">{{ __('extranet.upload_hint') }}</p>
                <button type="submit" class="a-btn a-btn--primary">{{ __('extranet.upload_action') }}</button>
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
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <th scope="row">
                                    <span class="e-file">
                                        <span class="e-file__format" aria-hidden="true">{{ strtoupper(pathinfo($document->original_filename, PATHINFO_EXTENSION)) }}</span>
                                        {{ $document->original_filename }}
                                    </span>
                                </th>
                                <td>{{ $document->uploader?->name ?? __('admin.system') }}</td>
                                <td data-quiet>{{ $document->created_at->isoFormat('D MMM YYYY') }}</td>
                                <td data-numeric>{{ number_format($document->size_bytes / 1024) }} KB</td>
                                <td>
                                    <span class="a-table__actions">
                                        {{-- Never a filesystem path. Every byte
                                             goes through the controller, which
                                             checks clearance, country and
                                             committee before streaming. --}}
                                        <a href="{{ route('documents.download', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">{{ __('extranet.download') }}</a>
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
            @if ($folder->country)
                <div class="a-row">
                    <span class="a-identity__initials" aria-hidden="true">{{ strtoupper($folder->country->iso_code) }}</span>
                    <span class="a-row__grow">{{ __('extranet.access_country', ['authority' => $folder->country->authorities->first()?->acronym ?? $folder->country->name]) }}</span>
                </div>
            @endif
            @if ($folder->committee)
                <div class="a-row">
                    <span class="a-identity__initials" aria-hidden="true">{{ mb_substr($folder->committee->name, 0, 2) }}</span>
                    <span class="a-row__grow">{{ __('extranet.access_committee', ['committee' => $folder->committee->name]) }}</span>
                </div>
            @endif
            <div class="a-row">
                <span class="a-identity__initials" aria-hidden="true">GB</span>
                <span class="a-row__grow">{{ __('extranet.access_secretariat') }}</span>
            </div>
        </div>
    </section>
@endsection
