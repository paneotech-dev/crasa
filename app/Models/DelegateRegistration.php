<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\CountryScoped;
use App\Models\Concerns\MarksSampleData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delegate's arrival and departure against one meeting. WS-07.
 *
 * THE LINE THIS MODEL SITS ON. The website collects; the Event Management
 * Platform manages. This record says who is coming, when they land and when
 * they leave. It says nothing about which car meets them, where they sleep
 * or what they eat, and it never will: those are the managing half and a
 * separate commercial agreement.
 */
class DelegateRegistration extends Model
{
    use Auditable, CountryScoped, HasFactory, MarksSampleData;

    protected $fillable = [
        'meeting_id', 'user_id', 'country_id',
        'delegate_name', 'delegate_email', 'delegate_role',
        'arrival_point', 'arrival_on', 'arrival_at', 'arrival_reference',
        'departure_on', 'departure_at', 'departure_reference',
        'notes', 'status', 'is_sample',
    ];

    protected function casts(): array
    {
        return [
            'arrival_on' => 'date',
            'departure_on' => 'date',
            'is_sample' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A delegate's own record is visible to them; the rest is Secretariat. */
    public function countryScopeIncludesShared(): bool
    {
        return false;
    }
}
