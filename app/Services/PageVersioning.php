<?php

namespace App\Services;

use App\Models\Page;
use App\Models\PageBlock;
use App\Models\PageVersion;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;

/**
 * Writes composed pages as versions.
 *
 * The same contract as InstrumentVersioning, applied to pages because the
 * legal pages need it: a privacy notice is a document a reader may have
 * relied on, and the audit log records that a block changed, not what it
 * said. A version records what it said.
 *
 * ORDER MATTERS. record() is called after the change and snapshots the
 * result, so the newest version is the current state. recordInitial() is
 * called before the change and only fires when the page has no history at
 * all, so the first tracked edit leaves both the before and the after
 * behind rather than a single row with nothing to compare it to.
 */
class PageVersioning
{
    /** The state a page reached, with who took it there and why. */
    public function record(Page $page, string $changeSummary, ?int $changedBy = null): PageVersion
    {
        return DB::transaction(function () use ($page, $changeSummary, $changedBy) {
            $page->unsetRelation('translations');
            $page->unsetRelation('blocks');
            $page->unsetRelation('editableBlocks');

            return PageVersion::create([
                'page_id' => $page->getKey(),
                'version_number' => $this->nextVersionNumber($page),
                'changed_by' => $changedBy ?? auth()->id(),
                'change_summary' => $changeSummary,
                'payload' => $this->snapshot($page),
            ]);
        });
    }

    /**
     * The state before the first tracked edit, recorded once.
     *
     * Returns null when the page already has history, which is every call
     * after the first.
     */
    public function recordInitial(Page $page): ?PageVersion
    {
        if (PageVersion::where('page_id', $page->getKey())->exists()) {
            return null;
        }

        return $this->record($page, __('admin.page_version_initial'));
    }

    private function nextVersionNumber(Page $page): int
    {
        return (int) PageVersion::where('page_id', $page->getKey())->max('version_number') + 1;
    }

    /**
     * The whole page: its status, its own translations, and every block
     * with everything an editor can change about it.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Page $page): array
    {
        return [
            'status' => $page->status->value,
            'template' => $page->template,
            'page' => $this->translations($page, $page->translatableFields()),
            'blocks' => $page->blocks()->with('translations')->get()
                ->map(fn (PageBlock $block) => [
                    'block_type' => $block->type()->value,
                    'position' => (int) $block->position,
                    'anchor' => $block->anchor,
                    'is_visible' => (bool) $block->is_visible,
                    'settings' => $block->settings ?? [],
                    'translations' => $this->translations($block, $block->translatableFields()),
                ])
                ->all(),
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return array<string, array<string, string>>
     */
    private function translations(Page|PageBlock $record, array $fields): array
    {
        $payload = [];

        foreach (Locales::SUPPORTED as $locale) {
            foreach ($fields as $field) {
                $value = $record->translationValuesFor($field)[$locale] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $payload[$locale][$field] = $value;
                }
            }
        }

        return $payload;
    }
}
