<?php

namespace Tests\Feature;

use App\Admin\ResourceRegistry;
use App\Enums\BlockType;
use App\Enums\NewsCategory;
use App\Enums\NotificationTrigger;
use App\Enums\Permission;
use App\Http\Controllers\Admin\TranslationController;
use App\Models\Concerns\Auditable;
use App\Models\NewsItem;
use App\Models\PageBlock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * No reader sees "news.category_press_release" (19 October 2026).
 *
 * A key with no translation is printed as the key itself, and nothing fails:
 * the legacy import added a press-release category whose label was never
 * written, and the public newsroom has shown the raw key in its filter in
 * all three languages since September. Two guards: every label the code
 * builds from a value exists in English, French and Portuguese; and no
 * visitor-facing page, crawled in each language, contains a raw key.
 */
class NoRawTranslationKeysTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['en', 'fr', 'pt'];

    /** @return list<string> */
    private function missing(array $keys): array
    {
        $missing = [];

        foreach (array_unique($keys) as $key) {
            foreach (self::LOCALES as $locale) {
                if (! Lang::has($key, $locale, false)) {
                    $missing[] = "{$key} ({$locale})";
                }
            }
        }

        return $missing;
    }

    public function test_every_label_built_from_a_value_exists_in_all_three_languages(): void
    {
        $keys = [];

        foreach (glob(app_path('Enums/*.php')) as $file) {
            $class = 'App\\Enums\\'.basename($file, '.php');

            if (enum_exists($class) && method_exists($class, 'labelKey')) {
                foreach ($class::cases() as $case) {
                    $keys[] = $case->labelKey();
                }
            }
        }

        foreach (ResourceRegistry::all() as $resource) {
            foreach ($resource->fields as $field) {
                $keys[] = 'admin.field_'.$field->name;

                if ($field->control === 'select') {
                    foreach ($field->options as $option) {
                        $keys[] = 'admin.option_'.$option;
                    }
                }
            }

            foreach ($resource->translatable as $field) {
                $keys[] = 'admin.field_'.$field;
            }
        }

        foreach ((new ReflectionClassConstant(TranslationController::class, 'TYPES'))->getValue() as $type => $class) {
            $keys[] = 'admin.trtype_'.$type;

            // A page block's fields depend on its type: every type counts.
            $fields = $class === PageBlock::class
                ? collect(BlockType::cases())->flatMap(fn (BlockType $t) => $t->translatableFields())->all()
                : (new $class)->translatableFields();

            foreach ($fields as $field) {
                $keys[] = 'admin.field_'.$field;
            }
        }

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (class_exists($class) && in_array(Auditable::class, class_uses_recursive($class), true)) {
                $keys[] = 'audit.subject_'.mb_strtolower(basename($file, '.php'));
            }
        }

        // The types stored with a notification, and every trigger.
        foreach (['submission', 'announcement'] as $type) {
            $keys[] = 'notifications.trigger_'.$type;
        }

        foreach (NotificationTrigger::cases() as $trigger) {
            $keys[] = 'notifications.trigger_'.$trigger->value;
        }

        foreach (Permission::cases() as $permission) {
            $keys[] = 'permissions.'.str_replace('.', '_', $permission->value);
        }

        $keys[] = 'admin.dashboard';

        $this->assertSame([], $this->missing($keys), 'Labels with no translation are printed as raw keys.');
    }

    public function test_no_visitor_facing_page_shows_a_raw_key_in_any_language(): void
    {
        $this->seed(DatabaseSeeder::class);

        // The category whose label was missing, so its filter is on the page.
        NewsItem::factory()->create(['category' => NewsCategory::PressRelease]);

        $files = array_map(fn ($f) => basename($f, '.php'), glob(lang_path('en/*.php')));
        $pattern = '/(?<![\w\/@:.-])('.implode('|', $files).')\.([a-z][a-z0-9_]*)(?![\w.]*\.(?:org|com|net|int|africa|tech)\b)(?!\.[a-z])/';

        $raw = [];
        $visited = 0;

        foreach (self::LOCALES as $locale) {
            $queue = ["/{$locale}", "/{$locale}/newsroom"];
            $seen = [];

            while ($queue !== [] && count($seen) < 70) {
                $path = array_shift($queue);

                if (isset($seen[$path])) {
                    continue;
                }

                $seen[$path] = true;
                $response = $this->get($path);

                if (! str_contains((string) $response->headers->get('Content-Type'), 'html')) {
                    continue;
                }

                $visited++;
                $html = (string) $response->getContent();

                // Visible text, plus the attributes a reader meets.
                $text = preg_replace('~<(script|style)\b.*?</\1>~si', ' ', $html);
                $text = preg_replace_callback('~<[^>]+>~', function ($tag) {
                    preg_match_all('~(?:alt|title|aria-label|placeholder|value)="([^"]*)"~', $tag[0], $attributes);

                    return ' '.implode(' ', $attributes[1]).' ';
                }, $text);
                $text = html_entity_decode($text, ENT_QUOTES);

                if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $key = $match[1].'.'.$match[2];

                        if (! Lang::has($key, 'en')) {
                            $raw[$key][] = $path;
                        }
                    }
                }

                preg_match_all('~href="(?:https?://[^/"]+)?(/'.$locale.'(?:/[^"#?]*)?)(?:\?[^"#]*)?"~', $html, $links);

                foreach ($links[1] as $link) {
                    if (! preg_match('~/(download|export)~', $link) && ! isset($seen[$link])) {
                        $queue[] = $link;
                    }
                }
            }
        }

        $this->assertGreaterThan(60, $visited, 'The crawl reached too few pages to mean anything.');
        $this->assertSame([], array_map(fn ($paths) => array_slice(array_unique($paths), 0, 3), $raw), 'Raw translation keys on visitor-facing pages.');
    }
}
