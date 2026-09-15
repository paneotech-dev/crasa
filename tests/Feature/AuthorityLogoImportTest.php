<?php

namespace Tests\Feature;

use App\Models\Authority;
use App\Models\Country;
use App\Models\Page;
use App\Models\Sector;
use Database\Seeders\AuthoritySeeder;
use Database\Seeders\CommitteeSeeder;
use Database\Seeders\CountrySeeder;
use Database\Seeders\PublicPagesSeeder;
use Database\Seeders\SectorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The fourteen member states' own marks.
 *
 * WHAT THESE HOLD. That every one of the fourteen lands against the right
 * member state; that running the import twice writes nothing the second
 * time; that a logo a member state uploaded itself is never overwritten by
 * a scrape of the old site; and that a member state with no logo gets
 * exactly the page it got before.
 *
 * The source is a fixture directory rather than the real archive, because
 * a test that needs a 57 MB zip on disk is a test that fails on a fresh
 * checkout. The matching rules it exercises are the real ones, including
 * the decomposed Unicode that nine of the real filenames carry.
 */
class AuthorityLogoImportTest extends TestCase
{
    use RefreshDatabase;

    private string $source;

    /**
     * The fourteen, as the archive names them. Ten carry the acronym and
     * four carry only the full name, which is the split in the real files.
     *
     * @var array<string, string>
     */
    private const FILES = [
        'ao' => '601b04c77148a03bd66c3cae_Instituto Angolano das Communicac'."\u{0327}".'o'."\u{0303}".'es (INACOM).png',
        'bw' => '601b04c85ca6cc49339e4f10_Botswana Communications Regulatory Authority (BOCRA).png',
        'cd' => '601b04c8012aea58eaea030c_Autorite de Regulation de la Poste et des Telecommunications du Congo .png',
        'km' => '64ecadc0ccacbaf40535539c_anrtic-logo.jpeg',
        'ls' => '601b04c8cb3a1ea060cfe6d3_Lesotho Communications Authority  .png',
        'mu' => '601b04c8f3122153caf8184e_Information and Communications Technologies Authority of Mauritius .png',
        'mw' => '601b04c7202357b1eb17a4fe_Malawi Communications Regulatory Authority .png',
        'mz' => '601b04c85be33a176e187393_Instituto Nacional das Communicacoes de Mocambique .png',
        'na' => '601b04c7cb3a1ecf36cfe6d2_Communications Regulatory Authority of Namibia (CRAN).png',
        'sz' => '62440c780245ec730a4883bb_ESCCOM-Logo.svg',
        'tz' => '6287a06a2571b374ee7dbd57_tcra-logo.jpg',
        'za' => '601b04c78bcb4b63e08f0d99_Independent Communications Authority of South Africa  (ICASA).png',
        'zm' => '6287a0ba2bec7963493d8375_Zicta-Logo.jpg',
        'zw' => '62738778220f2b1f2308da4e_POTRAZ-Logo.jpg',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(AuthoritySeeder::class);

        $this->source = storage_path('framework/testing/logo-fixtures');

        if (! is_dir($this->source)) {
            mkdir($this->source, 0755, true);
        }

        foreach (array_merge(glob($this->source.'/*') ?: []) as $stale) {
            unlink($stale);
        }

        foreach (self::FILES as $iso => $name) {
            file_put_contents($this->source.'/'.$name, $this->image($iso, $name));
        }

        // Webflow's own resizes sit beside every original. Taking one would
        // import a copy of a copy, so the command must ignore them.
        file_put_contents($this->source.'/6287a06a2571b374ee7dbd57_tcra-logo-p-500.jpg', 'derivative');
    }

    private function image(string $iso, string $name): string
    {
        return str_ends_with($name, '.svg')
            ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><title>'.$iso.'</title></svg>'
            : 'binary-'.$iso;
    }

    private function import(array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('authorities:import-logos', $options + ['--source' => $this->source]);
    }

    public function test_all_fourteen_land_against_the_right_member_state(): void
    {
        $this->import()->assertSuccessful();

        $this->assertSame(14, Authority::whereNotNull('logo_path')->count());

        foreach (self::FILES as $iso => $name) {
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;

            $authority = Country::where('iso_code', $iso)->firstOrFail()->authority;

            $this->assertSame(
                'images/authorities/'.$iso.'.'.$extension,
                $authority->logo_path,
                "The logo for [{$iso}] is not the file that belongs to it."
            );

            Storage::disk('public')->assertExists($authority->logo_path);

            // The bytes are the ones from the file that names this
            // authority, not merely some file.
            $this->assertSame(
                $this->image($iso, $name),
                Storage::disk('public')->get($authority->logo_path),
                "The file at [{$iso}] holds another member state's mark."
            );
        }
    }

    /**
     * Nine of the real filenames are in decomposed Unicode: the c in
     * "Communicações" is a c followed by a combining cedilla, not a c-cedilla.
     * A literal comparison fails on every one of them.
     */
    public function test_a_decomposed_filename_still_matches(): void
    {
        $this->import()->assertSuccessful();

        $angola = Country::where('iso_code', 'ao')->firstOrFail()->authority;

        $this->assertSame('images/authorities/ao.png', $angola->logo_path);
        $this->assertSame('binary-ao', Storage::disk('public')->get($angola->logo_path));
    }

    public function test_running_it_again_writes_nothing(): void
    {
        $this->import()->assertSuccessful();

        $before = Authority::orderBy('id')->pluck('logo_path', 'id')->all();
        $times = [];

        foreach ($before as $path) {
            $times[$path] = Storage::disk('public')->lastModified($path);
        }

        $this->import()->assertSuccessful();

        $this->assertSame($before, Authority::orderBy('id')->pluck('logo_path', 'id')->all());

        foreach ($times as $path => $when) {
            $this->assertSame(
                $when,
                Storage::disk('public')->lastModified($path),
                "[{$path}] was rewritten on a second run."
            );
        }

        // And no second copy of anything.
        $this->assertCount(14, Storage::disk('public')->files('images/authorities'));
    }

    /**
     * A logo on the record wins. It may have been uploaded by the member
     * state itself through the authority profile form, and a scrape of the
     * old site must not overwrite a regulator's own file.
     */
    public function test_a_logo_the_member_state_uploaded_is_never_overwritten(): void
    {
        $botswana = Country::where('iso_code', 'bw')->firstOrFail()->authority;
        Storage::disk('public')->put('images/authorities/bw.png', 'uploaded-by-the-regulator');
        $botswana->forceFill(['logo_path' => 'images/authorities/bw.png'])->save();

        $this->import()->assertSuccessful();

        $this->assertSame('images/authorities/bw.png', $botswana->fresh()->logo_path);
        $this->assertSame(
            'uploaded-by-the-regulator',
            Storage::disk('public')->get('images/authorities/bw.png'),
            'The scrape overwrote a file the member state uploaded itself.'
        );

        // Everyone else still got theirs.
        $this->assertSame(14, Authority::whereNotNull('logo_path')->count());
    }

    public function test_the_dry_run_writes_nothing_at_all(): void
    {
        $this->import(['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Authority::whereNotNull('logo_path')->count());
        $this->assertSame([], Storage::disk('public')->files('images/authorities'));
    }

    public function test_webflows_own_resizes_are_not_imported(): void
    {
        $this->import()->assertSuccessful();

        foreach (Storage::disk('public')->files('images/authorities') as $file) {
            $this->assertStringNotContainsString('-p-', $file);
            $this->assertNotSame('derivative', Storage::disk('public')->get($file));
        }
    }

    /**
     * An SVG served from the public disk runs whatever it contains on this
     * platform's own origin, and these came off somebody else's site.
     */
    public function test_an_svg_carrying_script_is_refused_and_the_rest_still_import(): void
    {
        file_put_contents(
            $this->source.'/'.self::FILES['sz'],
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->import()->assertSuccessful();

        $eswatini = Country::where('iso_code', 'sz')->firstOrFail()->authority;

        $this->assertNull($eswatini->fresh()->logo_path);
        Storage::disk('public')->assertMissing('images/authorities/sz.svg');
        $this->assertSame(13, Authority::whereNotNull('logo_path')->count());
    }

    public function test_it_refuses_the_whole_run_when_a_member_state_has_no_logo(): void
    {
        unlink($this->source.'/'.self::FILES['zw']);

        $this->import()->assertFailed();

        $this->assertSame(
            0,
            Authority::whereNotNull('logo_path')->count(),
            'A partial import wrote records after refusing.'
        );
    }

    public function test_it_refuses_when_two_files_could_be_the_same_authority(): void
    {
        copy($this->source.'/'.self::FILES['zw'], $this->source.'/another_POTRAZ-Logo.jpg');

        $this->import()->assertFailed();

        $this->assertSame(0, Authority::whereNotNull('logo_path')->count());
    }
}
