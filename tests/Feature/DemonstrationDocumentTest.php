<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Support\SampleDocumentPdf;
use Database\Seeders\PublicationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\SampleDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * A demonstration document opens as a document, not a blank page.
 *
 * The placeholder PDF the publication seeder writes had one ">" too many after
 * its stream length. Readers could not parse the page content, so every
 * demonstration download was a blank page and every thumbnail was white. The
 * demonstration script downloads one of these in front of the room.
 */
class DemonstrationDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function placeholder(): string
    {
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PublicationSeeder::class);

        $files = Storage::disk('local')->files('documents');
        $this->assertNotEmpty($files, 'The seeder wrote no demonstration document.');

        return Storage::disk('local')->get($files[0]);
    }

    public function test_the_stream_dictionary_is_well_formed(): void
    {
        $pdf = $this->placeholder();

        $this->assertMatchesRegularExpression('/<<\/Length \d+>>stream\n/', $pdf);
        $this->assertStringNotContainsString('>>>stream', $pdf);
    }

    public function test_a_pdf_reader_finds_the_text(): void
    {
        $binary = (new ExecutableFinder)->find('pdftotext');

        if ($binary === null) {
            $this->markTestSkipped('pdftotext is not installed on this host.');
        }

        $file = tempnam(sys_get_temp_dir(), 'demo').'.pdf';
        file_put_contents($file, $this->placeholder());

        try {
            $process = new Process([$binary, '-q', $file, '-']);
            $process->run();

            $this->assertStringContainsString('Sample document', $process->getOutput());
            // A real document, not a one-line placeholder (14 October 2026).
            $this->assertGreaterThanOrEqual(3, substr_count($process->getOutput(), "\f"), 'Fewer than three pages.');
        } finally {
            @unlink($file);
        }
    }

    public function test_the_live_repair_seeder_replaces_a_placeholder_and_only_a_placeholder(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PublicationSeeder::class);

        $documents = Document::withoutGlobalScopes()->where('is_sample', true)->get();
        [$old, $kept] = [$documents[0], $documents[1]];
        $placeholder = "%PDF-1.4\n1 0 obj<<>>endobj\nBT (Demonstration document: x) Tj ET\n";
        Storage::disk('local')->put($old->file_path, $placeholder);
        $old->forceFill(['thumbnail_path' => 'thumbnails/old.png'])->save();
        $before = Storage::disk('local')->get($kept->file_path);

        $this->seed(SampleDocumentsSeeder::class);

        $this->assertStringContainsString('Sample document', Storage::disk('local')->get($old->file_path));
        $this->assertNull($old->fresh()->thumbnail_path);
        $this->assertSame($before, Storage::disk('local')->get($kept->file_path));
    }

    public function test_the_repair_seeder_replaces_a_paper_that_still_carries_the_old_banner(): void
    {
        Storage::fake('local');
        $this->seed(ReferenceDataSeeder::class);
        $this->seed(PublicationSeeder::class);

        $documents = Document::withoutGlobalScopes()->where('is_sample', true)->get();
        [$old, $kept] = [$documents[0], $documents[1]];

        // What the 14 October generator wrote on the title page.
        Storage::disk('local')->put($old->file_path, "%PDF-1.4\n".str_repeat('x', 4000)
            ."\nBT (SAMPLE DOCUMENT) Tj ET\nBT (Prepared for the demonstration of the CRASA platform. It is not an official CRASA document,) Tj ET\n");
        $old->forceFill(['thumbnail_path' => 'thumbnails/old.jpg'])->save();
        $before = Storage::disk('local')->get($kept->file_path);

        $this->seed(SampleDocumentsSeeder::class);

        $replaced = Storage::disk('local')->get($old->file_path);
        $this->assertStringNotContainsString('SAMPLE DOCUMENT', $replaced);
        $this->assertStringNotContainsString('Prepared for the demonstration', $replaced);
        $this->assertNull($old->fresh()->thumbnail_path, 'The old thumbnail shows the banner and must be drawn again.');
        $this->assertSame($before, Storage::disk('local')->get($kept->file_path));
    }

    public function test_the_current_generator_writes_no_banner(): void
    {
        $pdf = SampleDocumentPdf::forPublication('AGM communique 2025', 'communique', 2025);

        $this->assertStringNotContainsString('SAMPLE DOCUMENT', $pdf);
        $this->assertStringNotContainsString('Prepared for the demonstration', $pdf);
        $this->assertFalse(SampleDocumentPdf::isOutdated($pdf));
    }
}
