<?php

namespace Tests\Feature;

use Database\Seeders\PublicationSeeder;
use Database\Seeders\ReferenceDataSeeder;
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

            $this->assertStringContainsString('Demonstration document', $process->getOutput());
        } finally {
            @unlink($file);
        }
    }
}
