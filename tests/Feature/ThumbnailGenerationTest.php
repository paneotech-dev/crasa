<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Jobs\GenerateDocumentThumbnail;
use App\Models\Document;
use App\Models\Instrument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The thumbnail pipeline, run for real against Imagick and Ghostscript
 * rather than mocked. Both are present on this host and the page 1 round
 * trip was verified before the job was written.
 */
class ThumbnailGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function minimalPdf(): string
    {
        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            .'3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]/Contents 4 0 R'
            ."/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length 44>>stream\nBT /F1 24 Tf 20 100 Td (CRASA) Tj ET\nendstream\nendobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            ."trailer<</Root 1 0 R>>\n";
    }

    private function document(string $contents, string $mime = 'application/pdf'): Document
    {
        $document = Document::factory()->create([
            'documentable_type' => Instrument::class,
            'documentable_id' => Instrument::factory()->inForce()->create()->id,
            'clearance' => Clearance::Public,
            'file_path' => 'documents/sample.pdf',
            'mime_type' => $mime,
        ]);

        Storage::disk('local')->put($document->file_path, $contents);

        return $document;
    }

    public function test_page_one_of_a_pdf_becomes_a_thumbnail(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available on this host.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());

        (new GenerateDocumentThumbnail($document->id))->handle();

        $document->refresh();

        $this->assertNotNull($document->thumbnail_path, 'No thumbnail was written.');
        $this->assertTrue(Storage::disk('public')->exists($document->thumbnail_path));
        $this->assertGreaterThan(0, Storage::disk('public')->size($document->thumbnail_path));
    }

    public function test_the_thumbnail_is_a_jpeg_on_the_public_disk(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available on this host.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());
        (new GenerateDocumentThumbnail($document->id))->handle();

        $document->refresh();
        $bytes = Storage::disk('public')->get($document->thumbnail_path);

        $this->assertStringEndsWith('.jpg', $document->thumbnail_path);
        // JPEG magic number.
        $this->assertSame("\xFF\xD8\xFF", substr($bytes, 0, 3));
    }

    /**
     * A failed thumbnail is not a failed upload. The document keeps a null
     * path and the row falls back to the file-type icon.
     */
    public function test_an_unreadable_file_leaves_the_document_usable(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document('this is not a pdf at all');

        (new GenerateDocumentThumbnail($document->id))->handle();

        $this->assertNull($document->refresh()->thumbnail_path);
    }

    public function test_a_missing_source_file_does_not_throw(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());
        Storage::disk('local')->delete($document->file_path);

        (new GenerateDocumentThumbnail($document->id))->handle();

        $this->assertNull($document->refresh()->thumbnail_path);
    }

    public function test_a_non_pdf_is_skipped(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document('plain text', 'text/plain');

        (new GenerateDocumentThumbnail($document->id))->handle();

        $this->assertNull($document->refresh()->thumbnail_path);
    }

    public function test_a_deleted_document_does_not_break_the_queue(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());
        $id = $document->id;
        $document->forceDelete();

        (new GenerateDocumentThumbnail($id))->handle();

        $this->assertTrue(true);
    }

    public function test_the_command_rebuilds_a_thumbnail_whose_file_has_gone(): void
    {
        if (! class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick is not available on this host.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());
        $document->forceFill(['thumbnail_path' => 'thumbnails/'.$document->id.'.jpg'])->saveQuietly();

        $this->artisan('documents:thumbnails', ['--sync' => true])
            ->expectsOutput('Generated 1 thumbnail(s).')
            ->assertSuccessful();

        Storage::disk('public')->assertExists('thumbnails/'.$document->id.'.jpg');
    }

    public function test_the_command_leaves_a_present_thumbnail_alone(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $document = $this->document($this->minimalPdf());
        $document->forceFill(['thumbnail_path' => 'thumbnails/'.$document->id.'.jpg'])->saveQuietly();
        Storage::disk('public')->put('thumbnails/'.$document->id.'.jpg', 'existing');

        $this->artisan('documents:thumbnails', ['--sync' => true])
            ->expectsOutput('Every PDF already has a thumbnail.')
            ->assertSuccessful();

        $this->assertSame('existing', Storage::disk('public')->get('thumbnails/'.$document->id.'.jpg'));
    }
}
