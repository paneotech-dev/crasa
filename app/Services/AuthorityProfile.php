<?php

namespace App\Services;

use App\Models\Authority;
use App\Models\AuthorityChange;
use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * A member state's own record, proposed and applied. Extranet item 3.
 *
 * ONE APPLY STEP, SHARED WITH THE LILONGWE CONFIRMATION. Approving a
 * proposal and confirming a record in the room are the same act: a member
 * state standing behind its own regulator's details. They write the same
 * fields, clear the same is_sample flag and record the same confirmation.
 * Two implementations would drift, and the one that drifted would be the
 * one used once a year at a workshop.
 *
 * THE MANDATE IS THE SECTOR LIST. DATA_MODEL replaced the authorities
 * table's mandate_sectors column with the authority_sector pivot on
 * 8 September 2026, so which sectors a regulator holds is the mandate and
 * there is no separate prose field to keep in step with it.
 *
 * THE PROPOSAL IS THE WHOLE RECORD, not a diff. A representative fills in a
 * form that shows what the platform currently holds and changes what is
 * wrong; what they send is what they believe is right. Storing a diff would
 * mean a proposal approved three weeks later applied to a record that had
 * changed underneath it.
 */
final class AuthorityProfile
{
    /**
     * What the platform currently holds, in the shape a proposal takes.
     *
     * @return array<string, mixed>
     */
    public function current(Country $country): array
    {
        $authority = $country->authority;

        return [
            'name' => $authority?->name,
            'acronym' => $authority?->acronym,
            'website' => $authority?->website,
            'established_year' => $authority?->established_year,
            'logo_path' => $authority?->logo_path,
            'sectors' => $authority?->sectors->pluck('id')->all() ?? [],
            'committees' => $country->committees->pluck('id')->all(),
        ];
    }

    /**
     * Record what the member state says is right.
     *
     * One open proposal per member state. A second one would mean the
     * Secretariat reviewing two versions of the same record with no way to
     * tell which the state meant, so a further edit revises the open one.
     *
     * @param  array<string, mixed>  $payload
     */
    public function propose(Country $country, User $author, array $payload): AuthorityChange
    {
        /*
         * Nothing is created here. A proposal for a member state with no
         * authority record yet is legitimate, and creating a stub to hang
         * it off would put an unreviewed name on the public members page,
         * which is what this whole workflow exists to prevent.
         */
        /*
         * The scope already restricts a representative to their own member
         * state, and this method is also called for the Secretariat, whose
         * scope is not restricted. Either way the country_id below is the
         * one that decides.
         */
        $change = AuthorityChange::query()
            ->withoutGlobalScope(\App\Models\Scopes\CountryScope::class)
            ->where('country_id', $country->id)
            ->where('status', 'submitted')
            ->first();

        if ($change === null) {
            $change = new AuthorityChange([
                'authority_id' => $country->authority?->id,
                'country_id' => $country->id,
            ]);
        }

        $change->fill([
            'proposed_by' => $author->id,
            'payload' => $payload,
            'status' => 'submitted',
            'submitted_at' => now(),
            'review_note' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        return $change;
    }

    /**
     * Apply a proposal, or a confirmation made in the room.
     *
     * `$confirmedBy` is the delegate's own name, not the operator's account.
     * A confirmation that names the Secretariat operator driving the
     * projector rather than the delegate standing next to them is a
     * confirmation of nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    public function apply(Country $country, array $payload, User $actor, ?string $confirmedBy = null): Authority
    {
        return DB::transaction(function () use ($country, $payload, $actor, $confirmedBy): Authority {
            $authority = $country->authority ?? new Authority(['country_id' => $country->id]);

            $authority->fill(array_filter([
                'name' => $payload['name'] ?? null,
                'acronym' => $payload['acronym'] ?? null,
                'website' => $payload['website'] ?? null,
                'established_year' => $payload['established_year'] ?? null,
                'logo_path' => $payload['logo_path'] ?? null,
            ], fn ($value) => $value !== null));

            if ($confirmedBy !== null) {
                // Confirmed by its own regulator, so it is no longer
                // demonstration data and the go-live purge must not take it.
                $authority->is_sample = false;
                $authority->confirmed_at = now();
                $authority->confirmed_by_id = $actor->id;
                $authority->confirmed_by_name = $confirmedBy;
            }

            $authority->save();

            $authority->sectors()->sync($payload['sectors'] ?? []);

            $country->committees()->sync(
                collect($payload['committees'] ?? [])
                    ->mapWithKeys(fn ($id) => [$id => ['role' => 'member', 'is_sample' => $confirmedBy === null]])
                    ->all()
            );

            return $authority->refresh();
        });
    }
}
