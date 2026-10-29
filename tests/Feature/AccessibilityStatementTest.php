<?php

namespace Tests\Feature;

use App\Http\Controllers\AccessibilityStatementController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The accessibility statement, G5: one address per language, the standard,
 * the limitations with dates, how to report, the review date, in three
 * languages with the same structure.
 */
class AccessibilityStatementTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_language_has_its_own_address(): void
    {
        $this->get('/en/accessibility')->assertOk()->assertSee('<h1', false)->assertSee('Accessibility statement');
        $this->get('/fr/accessibilite')->assertOk()->assertSee("Déclaration d'accessibilité");
        $this->get('/pt/acessibilidade')->assertOk()->assertSee('Declaração de acessibilidade');
    }

    public function test_every_address_answers_in_every_language_so_the_switcher_never_404s(): void
    {
        foreach (['en', 'fr', 'pt'] as $locale) {
            foreach (AccessibilityStatementController::SLUGS as $slug) {
                $this->get("/{$locale}/{$slug}")->assertOk();
            }
        }
    }

    public function test_the_url_helper_gives_the_readers_own_address(): void
    {
        $this->assertStringEndsWith('/fr/accessibilite', AccessibilityStatementController::url('fr'));
        $this->assertStringEndsWith('/pt/acessibilidade', AccessibilityStatementController::url('pt'));
        $this->assertStringEndsWith('/en/accessibility', AccessibilityStatementController::url('en'));
    }

    public function test_the_statement_carries_what_a_statement_must(): void
    {
        $html = $this->get('/en/accessibility')->getContent();

        $this->assertStringContainsString('2.1', $html);
        $this->assertStringContainsString('Level AA', $html);
        $this->assertStringContainsStringIgnoringCase('Known exceptions', $html);
        $this->assertStringContainsString('1.4.11', $html);
        $this->assertStringContainsString('1.4.3', $html);
        $this->assertStringContainsString('mailto:', $html);
        $this->assertStringContainsString('datetime="2026-10-29"', $html);
        // A table of contents that points at every section.
        foreach (['standard', 'done', 'options', 'tested', 'limits', 'report', 'review'] as $id) {
            $this->assertStringContainsString('href="#'.$id.'"', $html);
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<th scope="row">', $html);
    }

    public function test_the_three_language_files_carry_the_same_keys(): void
    {
        $shape = function (array $a) use (&$shape) {
            $out = [];
            foreach ($a as $k => $v) {
                $out[$k] = is_array($v) ? $shape($v) : null;
            }

            return $out;
        };
        $en = $shape(require lang_path('en/g.php'));
        foreach (['fr', 'pt'] as $locale) {
            $this->assertSame($en, $shape(require lang_path($locale.'/g.php')), $locale);
        }
    }
}
