<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enquiry extends Model
{
    protected $fillable = [
        'name', 'email', 'organisation', 'country_id', 'subject',
        'message', 'locale', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function markDelivered(): void
    {
        $this->forceFill(['delivered_at' => now()])->save();
    }

    /** Enquiries stored but never notified. Something to check after an outage. */
    public function scopeUndelivered($query)
    {
        return $query->whereNull('delivered_at');
    }
}
