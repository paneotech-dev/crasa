@extends($layout)

{{--
    The recycle bin: files deleted from folders the reader may manage, kept
    thirty days and then purged by the scheduled documents:purge-bin.
--}}

@section('title', __('d3_docs.bin_title'))
@section('note', __('d3_docs.bin_note', ['days' => \App\Models\Document::BIN_DAYS]))

@section('crumbs')
    @if ($surface === 'admin')
        <li><a href="{{ route('admin.documents', ['locale' => app()->getLocale()]) }}">{{ __('d3_docs.nav_documents') }}</a></li>
    @else
        <li><a href="{{ route('extranet.folders', ['locale' => app()->getLocale()]) }}">{{ __('extranet.title') }}</a></li>
    @endif
    <li aria-current="page">{{ __('d3_docs.bin_title') }}</li>
@endsection

@section('content')
    @if ($documents->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('d3_docs.bin_empty_title') }}</div>
                <div class="a-empty__body">{{ __('d3_docs.bin_empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <caption class="c-sr">{{ __('d3_docs.bin_title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('extranet.col_file') }}</th>
                            <th scope="col">{{ __('d3_docs.col_folder') }}</th>
                            <th scope="col">{{ __('d3_docs.col_deleted_by') }}</th>
                            <th scope="col">{{ __('d3_docs.col_purged_on') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <th scope="row">{{ $document->original_filename }}</th>
                                <td>{{ $document->folder?->name }}</td>
                                <td>{{ $document->deleter?->name ?? __('admin.system') }}, {{ $document->deleted_at?->displayed()->isoFormat('D MMM YYYY') }}</td>
                                <td>{{ $document->purgeDate()?->displayed()->isoFormat('D MMM YYYY') }}</td>
                                <td class="a-table__actions">
                                    <form class="aw-inline-form" method="POST"
                                          action="{{ route($surface === 'admin' ? 'admin.documents.restore' : 'extranet.documents.restore', ['locale' => app()->getLocale(), 'document' => $document->id]) }}">
                                        @csrf @method('PUT')
                                        <button type="submit" class="a-linkbutton" aria-label="{{ __('d3_docs.restore_named', ['name' => $document->original_filename]) }}">{{ __('d3_docs.restore_action') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
