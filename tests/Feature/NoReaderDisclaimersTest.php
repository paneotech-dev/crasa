<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\IndicatorValue;
use App\Models\Instrument;
use App\Models\PageBlock;
use App\Models\Publication;
use App\Models\Translation;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\NeutralPublicTextSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * No reader sees a demonstration disclaimer (owner's instruction, 15 October 2026).
 *
 * The member state pages said "These details have not yet been confirmed",
 * figures carried "Demonstration value. Not reported ... not to be cited",
 * records opened "Demonstration record", legal pages showed bracketed notes
 * for counsel, and unwritten sections said "This section is being prepared".
 * The is_sample flags stay, so content:strip-samples still works.
 */
class NoReaderDisclaimersTest extends TestCase
{
    use RefreshDatabase;

    private const BANNED = '/not yet been confirmed|pas encore été confirm|ainda não foram confirm|demonstration (record|value|data|content|notice|figure|source)|d[ée]monstration :|de démonstration|de demonstração|not to be cited|should be cited|ne doit être cité|deve ser citad|being prepared|en préparation\. Le Secr|está a ser preparada|\[CRASA |\[La CRASA |\[A CRASA |Nothing written here yet/iu';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(ReferenceDataSeeder::class);
        User::factory()->memberStateRep(Country::where('iso_code', 'za')->sole())->create(['email' => 'za@example.test']);
        $this->seed(DatabaseSeeder::class);
    }

    public function test_no_public_or_member_page_shows_a_disclaimer(): void
    {
        $member = User::where('email', 'za@example.test')->sole();
        $instrument = Instrument::withoutGlobalScopes()->first();
        $publication = Publication::withoutGlobalScopes()->first();
        $paths = ['', '/about', '/members', '/members/za', '/members/tz', '/committees', '/publications', '/newsroom', '/events', '/indicators', '/comparator', '/comparator?mode=compare&countries=za,bw', '/privacy', '/terms', '/accessibility', '/search?q=spectrum'];
        if ($publication) {
            $paths[] = '/publications/'.$publication->slug;
        }

        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach ($paths as $path) {
                $html = (string) $this->get("/{$locale}{$path}")->getContent();
                $this->assertDoesNotMatchRegularExpression(self::BANNED, strip_tags($html), "/{$locale}{$path}");
            }
            foreach (['/extranet/dashboard', '/submissions', '/announcements', '/extranet/folders'] as $path) {
                $html = (string) $this->actingAs($member)->get("/{$locale}{$path}")->getContent();
                $this->assertDoesNotMatchRegularExpression(self::BANNED, strip_tags($html), "member /{$locale}{$path}");
            }
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_the_live_cleanup_rewrites_old_text_and_keeps_every_flag(): void
    {
        // Old-style text, as the live site had it before 15 October 2026.
        $value = IndicatorValue::where('is_sample', true)->first();
        $value->forceFill(['source_note' => 'Demonstration value. Not reported by the national regulator and not to be cited.'])->save();
        $instrument = Instrument::withoutGlobalScopes()->where('is_sample', true)->first();
        $instrument->setTranslation('summary', 'en', 'Demonstration record. The text of this instrument has not been supplied by Botswana and nothing here should be cited.');
        $terms = PageBlock::whereHas('page', fn ($q) => $q->where('slug', 'terms'))->first();
        $terms->setTranslation('body', 'en', "Availability. Provided as it is.\n\nGoverning law. **[CRASA to confirm the governing law.]**");
        $flags = [IndicatorValue::where('is_sample', true)->count(), Instrument::withoutGlobalScopes()->where('is_sample', true)->count()];

        $this->seed(NeutralPublicTextSeeder::class);
        $this->seed(NeutralPublicTextSeeder::class); // twice: nothing changes the second time

        $this->assertNull($value->fresh()->source_note);
        $this->assertStringStartsWith('Issued by the national regulator of', $instrument->fresh()->translate('summary', 'en')->value);
        // The drafting note stays in the text (the back office lists it) and is not shown.
        $this->assertStringContainsString('CRASA to confirm the governing law.', $terms->fresh()->translate('body', 'en')->value);
        $this->assertStringNotContainsString('CRASA to confirm the governing law', strip_tags($this->get('/en/terms')->getContent()));
        $this->assertSame($flags, [IndicatorValue::where('is_sample', true)->count(), Instrument::withoutGlobalScopes()->where('is_sample', true)->count()]);
        $this->assertSame(0, Translation::where('value', 'like', 'Demonstration record%')->count());
    }
}
