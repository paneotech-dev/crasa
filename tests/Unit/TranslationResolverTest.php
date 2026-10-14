<?php

namespace Tests\Unit;

use App\Support\TranslationResolver;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The resolution rule from docs/DATA_MODEL.md, section 5.
 */
class TranslationResolverTest extends TestCase
{
    public function test_it_returns_the_requested_language_when_present(): void
    {
        $resolved = TranslationResolver::resolve([
            'en' => 'Spectrum and Frequency Management',
            'pt' => 'Gestão do Espectro e das Frequências',
        ], 'pt');

        $this->assertNotNull($resolved);
        $this->assertSame('Gestão do Espectro e das Frequências', $resolved->value);
        $this->assertSame('pt', $resolved->locale);
        $this->assertFalse($resolved->isFallback());
    }

    public function test_it_falls_back_to_english_when_the_requested_language_is_missing(): void
    {
        $resolved = TranslationResolver::resolve([
            'en' => 'Spectrum and Frequency Management',
        ], 'pt');

        $this->assertNotNull($resolved);
        $this->assertSame('Spectrum and Frequency Management', $resolved->value);
        $this->assertSame('en', $resolved->locale);
        $this->assertTrue($resolved->isFallback());
    }

    public function test_an_empty_translation_counts_as_missing(): void
    {
        $resolved = TranslationResolver::resolve([
            'en' => 'Spectrum and Frequency Management',
            'fr' => '   ',
        ], 'fr');

        $this->assertNotNull($resolved);
        $this->assertSame('en', $resolved->locale);
        $this->assertTrue($resolved->isFallback());
    }

    public function test_it_hides_the_field_and_warns_when_only_another_language_has_text(): void
    {
        Log::shouldReceive('warning')->once();

        $resolved = TranslationResolver::resolve(['fr' => 'Loi type'], 'pt', [
            'field' => 'title',
            'translatable' => 'App\Models\Instrument#12',
        ]);

        $this->assertNull($resolved);
    }

    public function test_a_field_empty_in_every_language_is_hidden_without_a_warning(): void
    {
        Log::shouldReceive('warning')->never();
        Log::shouldReceive('debug')->once();

        $resolved = TranslationResolver::resolve(['fr' => null], 'pt', [
            'field' => 'title',
            'translatable' => 'App\Models\Instrument#12',
        ]);

        $this->assertNull($resolved);
    }

    public function test_it_uses_the_active_application_locale_when_none_is_given(): void
    {
        app()->setLocale('fr');

        $resolved = TranslationResolver::resolve([
            'en' => 'Model law',
            'fr' => 'Loi type',
        ]);

        $this->assertNotNull($resolved);
        $this->assertSame('Loi type', $resolved->value);
        $this->assertFalse($resolved->isFallback());
    }
}
