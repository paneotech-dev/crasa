<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * One version of a composed page. Append only, on the same terms as
 * InstrumentVersion: a version records what the page was, so rewriting one
 * would defeat the point.
 */
class PageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'page_id', 'version_number', 'changed_by', 'change_summary', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('A version records what the page was. It cannot be rewritten.');
        });

        static::deleting(function (): never {
            throw new RuntimeException('Version history cannot be deleted.');
        });

        static::creating(function (PageVersion $version): void {
            $version->created_at ??= now();
        });
    }

    /**
     * The change summary in the interface language. It is stored in the
     * language of whoever made the change, so a summary that matches one of
     * the platform's own lines in English, French or Portuguese is shown in
     * the reader's language; anything else is shown as written.
     */
    public function displaySummary(): string
    {
        $summary = (string) $this->change_summary;

        foreach (\App\Support\Locales::SUPPORTED as $locale) {
            foreach (['page_version_initial', 'page_version_details'] as $key) {
                if ($summary === __('admin.'.$key, [], $locale)) {
                    return __('admin.'.$key);
                }
            }
            foreach (\App\Enums\BlockType::cases() as $type) {
                foreach (['page_version_block_added', 'page_version_block_edited'] as $key) {
                    if ($summary === __('admin.'.$key, ['type' => $type->label($locale)], $locale)) {
                        return __('admin.'.$key, ['type' => $type->label()]);
                    }
                }
            }
        }

        return $summary;
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** The page's own title in one language as it stood at this version. */
    public function titleFor(string $locale): ?string
    {
        return $this->payload['page'][$locale]['title'] ?? null;
    }

    /** The body of the block at $position in one language, at this version. */
    public function blockBody(int $position, string $locale): ?string
    {
        foreach ($this->payload['blocks'] ?? [] as $block) {
            if (($block['position'] ?? null) === $position) {
                return $block['translations'][$locale]['body'] ?? null;
            }
        }

        return null;
    }
}
