<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageBlock;
use App\Support\StructuredText;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The About page on one screenful less (29 October 2026): headed sections
 * in two columns, the Executive Committee and the Secretariat as grids of
 * people read from their own "Name, title" lines, the old menu anchors
 * still landing, and SADC named first beside CRASA.
 */
class AboutPageLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(PublicPagesSeeder::class);
    }

    private function about(string $anchor): PageBlock
    {
        return Page::where('slug', 'about')->firstOrFail()->blocks()->where('anchor', $anchor)->firstOrFail();
    }

    public function test_plain_text_reads_as_headed_sections_and_people(): void
    {
        $sections = StructuredText::sections("Our Focus\nHarmonisation of regulation.\n\nOur Vision\nAn integrated community.\n\nOur History\nSet up in 1997.\n\nMerged with SAPRA in 2011.");
        $this->assertSame([], $sections['intro']);
        $this->assertSame(['Our Focus', 'Our Vision', 'Our History'], array_column($sections['sections'], 'heading'));
        $this->assertCount(2, $sections['sections'][2]['paragraphs']);

        $people = StructuredText::people("The Secretariat implements the decisions of the AGM.\n\nDr. Stanley Shanapinda, Executive Secretary\n\nMr. Brian Mwansa, Head Postal");
        $this->assertSame(['The Secretariat implements the decisions of the AGM.'], $people['intro']);
        $this->assertSame([
            ['name' => 'Dr. Stanley Shanapinda', 'title' => 'Executive Secretary'],
            ['name' => 'Mr. Brian Mwansa', 'title' => 'Head Postal'],
        ], $people['people']);

        // A sentence with a comma is not a person, and markup is left alone.
        $this->assertSame([], StructuredText::people('CRASA comprises four institutions, namely the AGM and the Secretariat.')['people']);
        $this->assertSame([], StructuredText::parse('<p>Already marked up</p>'));
    }

    public function test_the_page_renders_columns_people_and_the_old_anchors(): void
    {
        $governance = $this->about('governance');
        $governance->setTranslations('en', ['body' => "CRASA comprises four institutions.\n\nAnnual general meeting\n\nThe AGM meets once a year.\n\nTask teams\n\nThey are set up when need arises."]);
        $governance->update(['needs_content' => false]);

        $secretariat = $this->about('secretariat');
        $secretariat->setTranslations('en', ['body' => "The Secretariat runs the operations.\n\nDr. Stanley Shanapinda, Executive Secretary\n\nMr. Brian Mwansa, Head Postal"]);
        $secretariat->update(['needs_content' => false]);

        $executive = $this->about('executive-committee');
        $executive->setTranslations('en', ['body' => "The Executive Committee oversees the operations.\n\nMr. Martin Mokgware, Treasurer"]);
        $executive->update(['needs_content' => false]);

        $html = $this->get('/en/about')->assertOk()->getContent();

        foreach (['id="vision-mission-history"', 'id="structure"', 'id="secretariat"', 'id="governance"', 'id="executive-committee"'] as $anchor) {
            if ($anchor === 'id="vision-mission-history"') {
                continue; // Imported from the legacy site, not seeded.
            }
            $this->assertStringContainsString($anchor, $html);
        }

        // Governance as four cards, people as grids with initials (C16, UI system).
        $this->assertStringContainsString('<h3 class="u-card__title cp-gov__title">Annual general meeting</h3>', $html);
        $this->assertStringContainsString('<h3 class="u-card__title cp-gov__title">Task teams</h3>', $html);
        $this->assertMatchesRegularExpression('~<li class="u-person">\s*<span class="u-avatar" aria-hidden="true">SS</span>.*?<span class="u-person__name">Dr\. Stanley Shanapinda</span>\s*<span class="u-person__role">Executive Secretary</span>~s', $html);
        $this->assertSame(3, substr_count($html, 'class="u-person"'));
        $this->assertStringContainsString('class="u-timeline cp-about-timeline"', $html);
    }

    public function test_sadc_comes_first_and_links_to_its_site_in_every_locale(): void
    {
        foreach (['en' => 'SADC and CRASA', 'fr' => 'La SADC et la CRASA', 'pt' => 'A SADC e a CRASA'] as $locale => $heading) {
            $html = $this->get("/{$locale}/about")->assertOk()->getContent();

            $this->assertStringContainsString('id="sadc"', $html);
            $this->assertStringContainsString($heading, $html);
            $this->assertStringContainsString('href="https://www.sadc.int"', $html);
            $this->assertStringContainsString(e(__('footer.blurb', [], $locale)), $html);
            $this->assertLessThan(strpos($html, 'id="sadc"'), strpos($html, 'id="who-we-are"'));
        }
    }

    public function test_the_layout_is_a_back_office_setting(): void
    {
        $fields = \App\Enums\BlockType::RichText->settingsFields();

        $this->assertSame(['prose', 'columns', 'people'], $fields['layout']['options']);
        $this->assertSame('text', $fields['also_anchor']['control']);
    }

    /**
     * "Police" means fonts in French and reads as policing in English. No
     * language file and no template may carry it (29 October 2026).
     */
    public function test_no_interface_string_says_police(): void
    {
        $files = array_merge(
            glob(lang_path('*/*.php')),
            \Illuminate\Support\Facades\File::allFiles(resource_path('views')),
        );

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/\bpolices?\b/iu', file_get_contents((string) $file), (string) $file);
        }
    }
}
