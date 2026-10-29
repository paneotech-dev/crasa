@extends('layouts.admin')

{{--
    Social posts. CRASA's own posts on X, Facebook and LinkedIn, pasted in
    by address. The three most recent published ones are the home page's
    "Latest from CRASA"; all of them close the newsroom.
--}}

@section('title', __('fb.social_title'))
@section('note', __('fb.social_note'))

@section('crumbs')
    <li aria-current="page">{{ __('fb.social_title') }}</li>
@endsection

@section('actions')
    <a class="a-btn a-btn--primary" href="{{ route('admin.social.create') }}">{{ __('fb.social_add') }}</a>
@endsection

@section('content')
    @if ($posts->isEmpty())
        <div class="a-card">
            <div class="a-empty">
                <div class="a-empty__title">{{ __('fb.social_empty_title') }}</div>
                <div class="a-empty__body">{{ __('fb.social_empty_body') }}</div>
            </div>
        </div>
    @else
        <section class="a-card a-card--flush">
            <div class="a-tablewrap">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('fb.col_post') }}</th>
                            <th scope="col">{{ __('fb.col_network') }}</th>
                            <th scope="col">{{ __('fb.col_date') }}</th>
                            <th scope="col">{{ __('fb.col_embed') }}</th>
                            <th scope="col">{{ __('fb.col_status') }}</th>
                            <th scope="col">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('admin.social.edit', ['post' => $post->id]) }}">{{ $post->caption ?: \Illuminate\Support\Str::limit($post->url, 70) }}</a>
                                </th>
                                <td>{{ \App\Support\SocialAccounts::label($post->network) }}</td>
                                <td>{{ $post->posted_at?->isoFormat('D MMMM YYYY') }}</td>
                                <td>{{ $post->hasEmbed() ? __('fb.shown_embed') : __('fb.shown_card') }}</td>
                                <td><span class="a-pill" data-status="{{ $post->status }}">{{ __('fb.status_'.$post->status) }}</span></td>
                                <td class="a-table__actions">
                                    <a href="{{ route('admin.social.edit', ['post' => $post->id]) }}">{{ __('admin.edit') }}</a>
                                    <a href="{{ $post->url }}" target="_blank" rel="noopener">{{ __('fb.preview') }}</a>
                                    <form method="POST" action="{{ route('admin.social.destroy', ['post' => $post->id]) }}" class="a-table__inlineform"
                                          onsubmit="return confirm('{{ __('fb.delete_confirm') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="a-linkbutton a-linkbutton--danger">{{ __('admin.delete') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{ $posts->links() }}
    @endif
@endsection
