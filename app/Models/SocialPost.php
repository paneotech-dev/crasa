<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of CRASA's own posts on X, Facebook or LinkedIn.
 *
 * Saved as a draft. Somebody who may publish content puts it on the site,
 * the same rule as a news item.
 */
class SocialPost extends Model
{
    use Auditable;

    public const NETWORKS = ['x', 'facebook', 'linkedin'];

    protected $fillable = [
        'network', 'url', 'posted_at', 'caption', 'embed_html', 'embed_url',
        'status', 'published_at', 'author_id',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Published, newest post first. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->orderByDesc('posted_at')
            ->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Something the reader will see drawn, not only a link. */
    public function hasEmbed(): bool
    {
        return filled($this->embed_html) || filled($this->embed_url);
    }
}
