<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
use App\Support\Locales;
use Database\Factories\PageBlockFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PageBlock extends Model
{
    /** @use HasFactory<PageBlockFactory> */
    use Auditable, HasFactory, HasTranslations, MarksSampleData;

    protected $fillable = [
        'page_id', 'block_type', 'position', 'anchor', 'is_visible', 'settings',
        'is_sample', 'needs_content', 'content_note', 'imported_from_legacy',
    ];

    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'settings' => 'array',
            'is_visible' => 'boolean',
            'is_sample' => 'boolean',
            'needs_content' => 'boolean',
            'imported_from_legacy' => 'boolean',
        ];
    }

    /**
     * Settings are validated on every write, not only in a form request.
     * An editor cannot give a pillars block five columns or a call to action
     * an arbitrary colour, whatever reaches the model.
     */
    protected static function booted(): void
    {
        static::saving(function (PageBlock $block): void {
            $block->assertSettingsAreValid();
        });
    }

    public function assertSettingsAreValid(): void
    {
        $type = $this->block_type instanceof BlockType
            ? $this->block_type
            : BlockType::from($this->block_type);

        $validator = Validator::make($this->settings ?? [], $type->settingsRules());

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'settings' => $validator->errors()->all(),
            ]);
        }
    }

    /** @var list<string> */
    protected array $translatable = [];

    public function translatableFields(): array
    {
        return $this->type()->translatableFields();
    }

    public function type(): BlockType
    {
        return $this->block_type instanceof BlockType
            ? $this->block_type
            : BlockType::from($this->block_type);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /** A setting with its default, so a template never guards for null. */
    /**
     * A text section nobody has written yet: no body, or only the holding line
     * ("This section is being prepared"). Not shown to readers, and no link
     * points at it (15 October 2026).
     */
    public function isUnwritten(): bool
    {
        if ($this->block_type !== BlockType::RichText) {
            return false;
        }

        $body = $this->translate('body');

        return $body === null || in_array(trim($body->value), self::holdingLines(), true);
    }

    /** @return list<string> */
    public static function holdingLines(): array
    {
        return array_map(fn (string $locale) => __('pages.awaiting_content', [], $locale), Locales::SUPPORTED);
    }

    /**
     * Anchors of unwritten sections, by page path ("about" => ["partnerships"]).
     *
     * @return array<string, list<string>>
     */
    public static function unwrittenAnchors(): array
    {
        $out = [];
        foreach (self::query()->whereNotNull('anchor')->where('is_visible', true)->where('block_type', BlockType::RichText->value)->with(['translations', 'page'])->get() as $block) {
            if ($block->page !== null && $block->isUnwritten()) {
                $out[$block->page->slug][] = $block->anchor;
            }
        }

        return $out;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        $defaults = $this->type()->defaultSettings();

        return data_get($settings, $key) ?? data_get($defaults, $key) ?? $default;
    }
}
