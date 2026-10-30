{{-- Item 1b: machine drafts written when an item was approved, for review. --}}
<section class="a-panel" aria-labelledby="mt-review-title" style="margin-bottom:var(--s-5)">
    <div class="a-panel__head">
        <h2 id="mt-review-title">{{ __('m3_mt.review_title') }}</h2>
        <span class="a-panel__meta">{{ __('m3_mt.review_meta') }}</span>
    </div>

    @unless ($machineConfigured)
        <p class="a-notice" data-mt-unconfigured style="margin:var(--s-4)">{{ __('m3_mt.not_configured') }}</p>
    @endunless

    @if ($approvedDrafts === [])
        <div class="a-empty" style="padding:var(--s-4)">
            <div class="a-empty__title">{{ __('m3_mt.empty_title') }}</div>
        </div>
    @else
        <div class="a-tablewrap">
            <table class="a-table" data-mt-review>
                <thead>
                    <tr>
                        <th scope="col">{{ __('m3_mt.col_item') }}</th>
                        <th scope="col">{{ __('m3_mt.col_type') }}</th>
                        <th scope="col">{{ __('m3_mt.col_languages') }}</th>
                        <th scope="col">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($approvedDrafts as $draft)
                        @php $editUrl = route('admin.translations.edit', ['locale' => app()->getLocale(), 'type' => $draft['type'], 'id' => $draft['record']->getKey()]); @endphp
                        <tr>
                            <th scope="row"><a href="{{ $draft['item_url'] }}">{{ $draft['label'] }}</a></th>
                            <td>{{ __('admin.trtype_'.$draft['type']) }}</td>
                            <td>{{ collect($draft['locales'])->map(fn ($l) => \App\Support\Locales::nameOf($l))->implode(', ') }}</td>
                            <td>
                                <span class="a-table__actions">
                                    <a href="{{ $editUrl }}">{{ __('m3_mt.review_action') }}</a>
                                    <a href="{{ $draft['item_url'] }}">{{ __('m3_mt.open_item') }}</a>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
