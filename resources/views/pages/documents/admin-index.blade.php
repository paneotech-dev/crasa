@extends('layouts.admin')

{{--
    The member area's folders, from the back office: the whole tree, three
    levels at most, with who may see each folder in the words the folder
    page uses. Opening a folder goes to the folder page itself, where its
    files are managed.
--}}

@section('title', __('d3_docs.nav_documents'))
@section('note', __('d3_docs.admin_note'))

@section('crumbs')
    <li aria-current="page">{{ __('d3_docs.nav_documents') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--secondary" href="{{ route('admin.documents.bin', ['locale' => app()->getLocale()]) }}">{{ __('d3_docs.bin_title') }} ({{ $binCount }})</a>
    <a class="a-btn a-btn--primary" href="{{ route('admin.documents.create', ['locale' => app()->getLocale()]) }}">{{ __('d3_docs.new_folder') }}</a>
@endsection

@section('content')
    @if ($rows->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('extranet.empty_title') }}</div>
                <div class="a-empty__body">{{ __('d3_docs.admin_empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <caption class="c-sr">{{ __('d3_docs.nav_documents') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('d3_docs.col_folder') }}</th>
                            <th scope="col">{{ __('d3_docs.col_owner') }}</th>
                            <th scope="col">{{ __('d3_docs.field_clearance') }}</th>
                            <th scope="col">{{ __('extranet.who_can_see') }}</th>
                            <th scope="col" data-numeric>{{ __('d3_docs.col_files') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php($folder = $row['folder'])
                            <tr>
                                <th scope="row">
                                    <span class="d3d-tree-name" data-depth="{{ min(3, $row['depth']) }}">
                                        <a href="{{ route('extranet.folder', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}">{{ $folder->name }}</a>
                                    </span>
                                </th>
                                <td>{{ $folder->country?->displayName() ?? $folder->committee?->displayName() ?? __('extranet.regional') }}</td>
                                <td><span class="a-pill" data-status="{{ $folder->clearance->value }}">{{ $folder->clearance->label() }}</span></td>
                                <td>{{ \App\Support\Documents\FolderAccess::summary($folder)[0] }}</td>
                                <td data-numeric>{{ $folder->documents_count }}</td>
                                <td class="a-table__actions">
                                    <a href="{{ route('admin.documents.edit', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}" aria-label="{{ __('d3_docs.edit_named', ['name' => $folder->name]) }}">{{ __('admin.edit') }}</a>
                                    @if ($row['depth'] < \App\Models\Folder::MAX_DEPTH)
                                        <a href="{{ route('admin.documents.create', ['locale' => app()->getLocale(), 'parent' => $folder->id]) }}" aria-label="{{ __('d3_docs.new_subfolder_in', ['name' => $folder->name]) }}">{{ __('d3_docs.new_subfolder') }}</a>
                                    @endif
                                    @can('delete', $folder)
                                        <form class="aw-inline-form" method="POST" action="{{ route('admin.documents.destroy', ['locale' => app()->getLocale(), 'folder' => $folder->id]) }}"
                                              data-confirm="{{ __('d3_docs.delete_folder_confirm', ['name' => $folder->name]) }}" data-confirm-title="{{ __('d3_docs.delete_folder') }}" data-confirm-action="{{ __('d3_docs.delete_folder') }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="a-linkbutton a-linkbutton--danger" aria-label="{{ __('d3_docs.delete_named', ['name' => $folder->name]) }}">{{ __('fa.delete') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
