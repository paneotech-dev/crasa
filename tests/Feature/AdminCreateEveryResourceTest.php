<?php

namespace Tests\Feature;

use App\Admin\ResourceRegistry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Every admin resource can be created from its form (15 October 2026).
 *
 * The live audit found that creating an authority or an indicator answered
 * 500: the database required columns (acronym; category, data type,
 * definition, unit, periodicity, source) that the form left optional or did
 * not offer at all. This builds, for every creatable resource, the smallest
 * submission its own rules accept, posts it as an administrator, and fails on
 * anything but a saved record.
 */
class AdminCreateEveryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_smallest_valid_form_of_every_resource_saves(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $admin = User::factory()->administrator()->create();

        $failures = [];
        foreach (ResourceRegistry::all() as $key => $resource) {
            if (! $resource->creatable) {
                continue;
            }

            $payload = [];
            foreach ($resource->rules as $field => $rules) {
                $rules = is_string($rules) ? explode('|', $rules) : $rules;
                if (! in_array('required', $rules, true)) {
                    continue;
                }
                $payload[$field] = $this->valueFor($field, $rules);
            }
            foreach ($resource->translatable as $field) {
                $payload['en'][$field] = '[QA] '.$key.' '.$field;
            }

            $before = $resource->model::query()->withoutGlobalScopes()->count();
            $response = $this->actingAs($admin)->post("/en/admin/data/{$key}", $payload);
            $after = $resource->model::query()->withoutGlobalScopes()->count();

            if ($response->getStatusCode() >= 500 || $after !== $before + 1) {
                $errors = session('errors')?->all() ?? [];
                $failures[] = "{$key}: status {$response->getStatusCode()}, rows {$before} -> {$after}".($errors ? ' errors: '.implode('; ', $errors) : '');
            }
        }

        $this->assertSame([], $failures, implode("\n", $failures));
    }

    public function test_an_invalid_choice_is_a_message_not_a_server_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $admin = User::factory()->administrator()->create();

        foreach (['instruments' => 'instrument_type', 'publications' => 'publication_type', 'news' => 'category', 'indicators' => 'periodicity'] as $key => $field) {
            $this->actingAs($admin)->from("/en/admin/data/{$key}/new")
                ->post("/en/admin/data/{$key}", [$field => 'not-a-value'])
                ->assertRedirect("/en/admin/data/{$key}/new")
                ->assertSessionHasErrors($field);
        }
    }

    public function test_a_second_figure_for_the_same_period_is_a_message_not_a_server_error(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $admin = User::factory()->administrator()->create();
        $figure = ['indicator_id' => DB::table('indicators')->value('id'), 'country_id' => DB::table('countries')->value('id'), 'period' => '1999', 'value_numeric' => 1];

        $this->actingAs($admin)->post('/en/admin/data/indicator-values', $figure)->assertSessionHasNoErrors();
        $this->actingAs($admin)->from('/en/admin/data/indicator-values/new')
            ->post('/en/admin/data/indicator-values', $figure)
            ->assertRedirect('/en/admin/data/indicator-values/new')
            ->assertSessionHasErrors('period');
    }

    public function test_a_damaged_image_is_a_message_not_a_server_error(): void
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('Imagick is not installed.');
        }
        Storage::fake('public');
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $admin = User::factory()->administrator()->create();
        // A PNG signature and header followed by a corrupt image chunk.
        $png = UploadedFile::fake()->createWithContent('damaged.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAFklEQVR4nGNk+M9AEmAcVTCqYFQBVQEA0bAEEQ2gFG0AAAAASUVORK5CYII='));

        $this->actingAs($admin)->from('/en/admin/media/upload')
            ->post('/en/admin/media', ['file' => $png])
            ->assertRedirect('/en/admin/media/upload')
            ->assertSessionHasErrors('file');
    }

    public function test_a_required_text_area_is_marked_required(): void
    {
        $this->seed(DatabaseSeeder::class);
        config(['crasa.two_factor_required' => false]);
        $html = $this->actingAs(User::factory()->administrator()->create())->get('/en/admin/data/indicators/new')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<textarea[^>]*name="definition"[^>]*required/', $html);
    }

    private function valueFor(string $field, array $rules): mixed
    {
        foreach ($rules as $rule) {
            if (! is_string($rule)) {
                continue;
            }
            if (str_starts_with($rule, 'in:')) {
                return explode(',', substr($rule, 3))[0];
            }
            if (str_starts_with($rule, 'exists:')) {
                [$table, $column] = array_pad(explode(',', substr($rule, 7)), 2, 'id');

                return DB::table($table)->value($column);
            }
        }

        return match (true) {
            in_array('url', $rules, true) => 'https://example.org/qa',
            in_array('email', $rules, true) => 'qa@example.org',
            in_array('integer', $rules, true), in_array('numeric', $rules, true) => 1999,
            in_array('date', $rules, true) => now()->toDateString(),
            in_array('boolean', $rules, true) => 1,
            $field === 'period' => '1999',
            // Day 3: member states are creatable; the code is two letters.
            $field === 'iso_code' => 'qa',
            default => '[QA] '.Str::limit($field, 10, ''),
        };
    }
}
