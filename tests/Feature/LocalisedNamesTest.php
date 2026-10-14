<?php

namespace Tests\Feature;

use App\Models\Committee;
use App\Models\Country;
use App\Models\Indicator;
use App\Models\Sector;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Country, sector, committee and indicator names in the reader's language.
 *
 * Until 14 October 2026 a French page said "South Africa" and
 * "Telecommunications": those names are one English column. They now come
 * from lang/{fr,pt}/names.php, and never in the back office, where an edit
 * form would otherwise save the French name over the English one.
 */
class LocalisedNamesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_every_record_has_a_french_and_a_portuguese_name(): void
    {
        foreach (['fr', 'pt'] as $locale) {
            app()->setLocale($locale);
            foreach ([Country::all(), Sector::all(), Committee::all(), Indicator::all()] as $records) {
                foreach ($records as $record) {
                    $key = $record->iso_code ?? $record->slug;
                    $group = ['Country' => 'countries', 'Sector' => 'sectors', 'Committee' => 'committees', 'Indicator' => 'indicators'][class_basename($record)];
                    $path = $group === 'indicators' ? "names.{$group}.{$key}.name" : "names.{$group}.{$key}";
                    $this->assertTrue(trans()->hasForLocale($path, $locale), "No {$locale} name for {$group} {$key}.");
                }
            }
        }

        foreach (Indicator::all() as $indicator) {
            foreach (['fr', 'pt'] as $locale) {
                $this->assertTrue(trans()->hasForLocale("names.indicators.{$indicator->slug}.definition", $locale), "No {$locale} definition for {$indicator->slug}.");
            }
        }
    }

    public function test_the_name_follows_the_language_and_the_column_is_untouched(): void
    {
        $za = Country::where('iso_code', 'za')->sole();

        app()->setLocale('fr');
        $this->assertSame('Afrique du Sud', $za->name);
        app()->setLocale('pt');
        $this->assertSame('África do Sul', $za->name);
        app()->setLocale('en');
        $this->assertSame('South Africa', $za->name);

        $this->assertSame('South Africa', $za->rawName());
        $this->assertDatabaseHas('countries', ['iso_code' => 'za', 'name' => 'South Africa']);
    }

    public function test_public_pages_show_the_translated_names(): void
    {
        $this->get('/fr/members')->assertOk()->assertSee('Afrique du Sud');
        $this->get('/pt/members')->assertOk()->assertSee('África do Sul');
    }

    public function test_the_back_office_always_shows_and_saves_the_column(): void
    {
        $admin = User::factory()->administrator()->create();
        config(['crasa.two_factor_required' => false]);
        $za = Country::where('iso_code', 'za')->sole();

        $html = $this->actingAs($admin)->get('/fr/admin/data/countries/'.$za->id)->getContent();

        $this->assertStringContainsString('South Africa', $html);
        $this->assertStringNotContainsString('value="Afrique du Sud"', $html);
    }
}
