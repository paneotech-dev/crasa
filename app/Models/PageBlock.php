<?php

namespace App\Models;

use App\Enums\BlockType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\MarksSampleData;
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

    protected $fillable = ['page_id', 'block_type', 'position', 'is_visible', 'settings', 'is_sample'];

    protected function casts(): array
    {
        return [
            'block_type' => BlockType::class,
            'settings' => 'array',
            'is_visible' => 'boolean',
            'is_sample' => 'boolean',
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
    public function setting(string $key, mixed $default = null): mixed
    {
        $settings = $this->settings ?? [];
        $defaults = $this->type()->defaultSettings();

        return data_get($settings, $key) ?? data_get($defaults, $key) ?? $default;
    }
}
