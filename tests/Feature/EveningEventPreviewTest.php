<?php

namespace Tests\Feature;

use App\Enums\Clearance;
use App\Models\Meeting;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evening pass: the month calendar's chip preview. The chip stays a link to
 * the event page; the preview is an inert template shown in one native
 * dialog by events.js.
 */
class EveningEventPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->travelTo('2026-10-29 10:00:00');
    }

    public function test_each_chip_links_to_its_event_and_has_a_preview_with_title_dates_place_committee_calendar_and_open(): void
    {
        $m = Meeting::create([
            'title' => 'Postal Committee in November', 'slug' => 'postal-november', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-12',
            'location' => 'Windhoek, Namibia', 'clearance' => Clearance::Public, 'committee_code' => 'PC',
        ]);

        $html = $this->get('/en/events?view=month&month=2026-11')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a data-preview="e-pv-'.$m->id.'" class="e-chip[^"]*"[^>]*href="http://localhost/en/events/postal-november"~', $html);
        $start = strpos($html, '<template id="e-pv-'.$m->id.'">');
        $this->assertNotFalse($start);
        $tpl = substr($html, $start, strpos($html, '</template>', $start) - $start);

        foreach (['Postal Committee in November', '10 to 12 November 2026', 'Windhoek, Namibia', 'Postal Committee', __('events.add_to_calendar'), 'Google', 'Outlook', '.ics', __('evening.preview_open'), 'id="e-pv-title"', 'id="pv-addcal-'] as $needle) {
            $this->assertStringContainsString($needle, $tpl);
        }
        $this->assertStringContainsString('<dialog class="e-pv" aria-labelledby="e-pv-title" data-preview-dialog>', $html);
        $this->assertStringContainsString(__('evening.preview_close'), $html);

        $js = file_get_contents(public_path('js/events.js'));
        $this->assertStringContainsString('showModal()', $js);
        $this->assertStringContainsString("addEventListener('close'", $js);
        $this->assertStringContainsString('opener.focus()', $js);
    }

    public function test_an_empty_month_has_no_dialog(): void
    {
        $html = $this->get('/en/events?view=month&month=2026-12')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-preview-dialog', $html);
    }
}
