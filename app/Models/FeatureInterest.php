<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account saying it would use something that is not built.
 *
 * See the migration for why this exists and what it is not. Nothing reads
 * it to decide what to render.
 */
class FeatureInterest extends Model
{
    public $timestamps = false;

    /** The proposals a reader can express interest in. A fixed list. */
    public const ASSISTANT = 'regulatory-assistant';

    /** @var list<string> */
    public const FEATURES = [self::ASSISTANT];

    protected $fillable = ['user_id', 'feature', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function recorded(?User $user, string $feature): bool
    {
        return $user !== null
            && static::where('user_id', $user->getKey())->where('feature', $feature)->exists();
    }
}
