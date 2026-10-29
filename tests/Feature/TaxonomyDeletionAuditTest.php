<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Taxonomies;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every taxonomy kind leaves a deletion in the audit log. Categories,
 * publication types and sectors did; a tag deleted under Content >
 * Taxonomies left no trace.
 */
class TaxonomyDeletionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_term_of_every_kind_is_audited_with_who(): void
    {
        $this->seed(SectorSeeder::class);
        $admin = User::factory()->administrator()->create();

        foreach (array_keys(Taxonomies::KINDS) as $kind) {
            $model = Taxonomies::model($kind);
            $slug = 'audit-'.substr(md5($kind), 0, 6);

            $this->actingAs($admin)->post('/en/admin/taxonomies/'.$kind, array_filter([
                'name' => ['en' => 'Audit '.$kind, 'fr' => 'Audit fr '.$kind, 'pt' => 'Audit pt '.$kind],
                'slug' => $slug,
                'sort_order' => 90,
                'colour' => Taxonomies::hasColour($kind) ? 'green' : null,
            ]))->assertRedirect()->assertSessionHasNoErrors();

            $term = $model::query()->where('slug', $slug)->sole();

            $this->actingAs($admin)->delete('/en/admin/taxonomies/'.$kind.'/'.$term->getKey())->assertRedirect()->assertSessionHasNoErrors();

            $this->assertNull($model::query()->find($term->getKey()), $kind);
            $this->assertTrue(AuditLog::query()
                ->where('action', AuditLog::DELETED)
                ->where('auditable_type', $term->getMorphClass())
                ->where('auditable_id', $term->getKey())
                ->where('user_id', $admin->id)
                ->exists(), $kind.' deletion is not audited');
        }
    }
}
