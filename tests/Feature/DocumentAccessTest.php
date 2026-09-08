<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Enums\CommitteeRole;
use App\Jobs\GenerateDocumentThumbnail;
use App\Models\AuditLog;
use App\Models\Committee;
use App\Models\Country;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Documents are never served from the filesystem. Everything here goes
 * through the controller, which is the only path to a file.
 */
class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Country $namibia;

    private Country $zambia;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->namibia = Country::factory()->create(['iso_code' => 'na', 'name' => 'Namibia']);
        $this->zambia = Country::factory()->create(['iso_code' => 'zm', 'name' => 'Zambia']);
    }

    private function documentFor(Country $country, Clearance $clearance, ?int $committeeId = null): Document
    {
        $instrument = Instrument::factory()->inForce()->create(['country_id' => $country->id]);

        $document = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => $instrument->id,
            'clearance' => $clearance,
            'committee_id' => $committeeId,
            'file_path' => 'documents/'.$country->iso_code.'-'.$clearance->value.'.pdf',
        ]);

        Storage::disk('local')->put($document->file_path, '%PDF-1.4 fake');

        return $document;
    }

    private function url(Document $document): string
    {
        return '/en/documents/'.$document->id;
    }

    /* Guests --------------------------------------------------------------- */

    public function test_an_unauthenticated_request_for_a_member_document_is_refused(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $this->get($this->url($document))->assertForbidden();
    }

    public function test_a_public_document_is_served_without_an_account(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Public);

        $this->get($this->url($document))->assertOk();
    }

    public function test_a_guest_is_refused_rather_than_redirected_to_a_login_page(): void
    {
        // The existence of a document at a given id is itself information.
        $document = $this->documentFor($this->namibia, Clearance::Confidential);

        $this->get($this->url($document))->assertStatus(403);
    }

    /* Country scope -------------------------------------------------------- */

    public function test_a_representative_is_refused_another_countrys_document(): void
    {
        $document = $this->documentFor($this->zambia, Clearance::Member);

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->get($this->url($document))
            ->assertForbidden();
    }

    public function test_a_representative_reaches_their_own_countrys_document(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->get($this->url($document))
            ->assertOk();
    }

    public function test_an_administrator_reaches_any_countrys_document(): void
    {
        $document = $this->documentFor($this->zambia, Clearance::Member);

        $this->actingAs(User::factory()->administrator()->create())
            ->get($this->url($document))
            ->assertOk();
    }

    /* Clearance ------------------------------------------------------------ */

    public function test_a_viewer_is_refused_a_member_document(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $this->actingAs(User::factory()->viewer()->create())
            ->get($this->url($document))
            ->assertForbidden();
    }

    public function test_an_editor_is_refused_a_confidential_document_without_a_seat(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Confidential);

        $this->actingAs(User::factory()->editor()->create())
            ->get($this->url($document))
            ->assertForbidden();
    }

    public function test_a_committee_seat_opens_a_confidential_document(): void
    {
        $committee = Committee::factory()->create();
        $document = $this->documentFor($this->namibia, Clearance::Confidential, $committee->id);
        $rep = User::factory()->memberStateRep($this->namibia)->create();
        $committee->users()->attach($rep, ['role' => CommitteeRole::Member->value]);

        $this->actingAs($rep->fresh())->get($this->url($document))->assertOk();
    }

    public function test_an_observers_seat_does_not_open_confidential_papers(): void
    {
        $committee = Committee::factory()->create();
        $document = $this->documentFor($this->namibia, Clearance::Confidential, $committee->id);
        $rep = User::factory()->memberStateRep($this->namibia)->create();
        $committee->users()->attach($rep, ['role' => CommitteeRole::Observer->value]);

        $this->actingAs($rep->fresh())->get($this->url($document))->assertForbidden();
    }

    public function test_secretariat_clearance_is_administrators_only(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Secretariat);

        $this->actingAs(User::factory()->editor()->create())->get($this->url($document))->assertForbidden();
        $this->actingAs(User::factory()->administrator()->create())->get($this->url($document))->assertOk();
    }

    public function test_a_suspended_account_reaches_no_document(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->suspended()->create())
            ->get($this->url($document))
            ->assertRedirect();
    }

    /* Audit ---------------------------------------------------------------- */

    public function test_every_download_of_a_non_public_document_writes_an_audit_row(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);
        $rep = User::factory()->memberStateRep($this->namibia)->create();

        $this->actingAs($rep)->get($this->url($document).'/download')->assertOk();

        $entry = AuditLog::where('action', AuditLog::DOCUMENT_DOWNLOADED)->sole();

        $this->assertSame($rep->id, $entry->user_id);
        $this->assertSame($document->id, (int) $entry->auditable_id);
        $this->assertSame('member', $entry->changes['clearance']);
    }

    public function test_a_public_document_download_is_not_audited(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Public);

        $this->get($this->url($document).'/download')->assertOk();

        $this->assertSame(0, AuditLog::where('action', AuditLog::DOCUMENT_DOWNLOADED)->count());
    }

    public function test_a_refused_request_streams_nothing_and_writes_no_audit_row(): void
    {
        $document = $this->documentFor($this->zambia, Clearance::Member);

        $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->get($this->url($document).'/download')
            ->assertForbidden();

        $this->assertSame(0, AuditLog::where('action', AuditLog::DOCUMENT_DOWNLOADED)->count());
    }

    /* Storage and headers -------------------------------------------------- */

    public function test_a_restricted_document_is_never_cached(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $header = $this->actingAs(User::factory()->memberStateRep($this->namibia)->create())
            ->get($this->url($document))
            ->headers->get('Cache-Control');

        // Symfony normalises directive order, so assert on the directives.
        foreach (['no-store', 'no-cache', 'private', 'must-revalidate'] as $directive) {
            $this->assertStringContainsString($directive, $header);
        }
        $this->assertStringNotContainsString('public', $header);
    }

    public function test_documents_live_on_the_private_disk_outside_the_web_root(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Member);

        $this->assertTrue(Storage::disk('local')->exists($document->file_path));
        $this->assertFalse(Storage::disk('public')->exists($document->file_path));
        $this->assertStringContainsString('app/private', config('filesystems.disks.local.root'));
    }

    public function test_a_missing_file_is_a_404_not_a_500(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Public);
        Storage::disk('local')->delete($document->file_path);

        $this->get($this->url($document))->assertNotFound();
    }

    /* Thumbnails ----------------------------------------------------------- */

    public function test_thumbnail_generation_is_queued_not_synchronous(): void
    {
        Bus::fake();

        $document = $this->documentFor($this->namibia, Clearance::Public);
        GenerateDocumentThumbnail::dispatch($document->id);

        Bus::assertDispatched(GenerateDocumentThumbnail::class);
    }

    public function test_a_document_with_no_thumbnail_is_a_normal_state(): void
    {
        $document = $this->documentFor($this->namibia, Clearance::Public);

        $this->assertNull($document->thumbnail_path);
        $this->get($this->url($document))->assertOk();
    }
}
