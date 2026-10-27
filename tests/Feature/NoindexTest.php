<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A demonstration host is kept out of search engines; a production host
 * is not touched by the switch.
 */
class NoindexTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_demonstration_host_says_noindex_in_the_page_and_the_header(): void
    {
        config(['crasa.noindex' => true]);

        $this->get('/en/login')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_without_the_switch_nothing_is_added(): void
    {
        config(['crasa.noindex' => false]);

        $response = $this->get('/en/login')->assertOk();

        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $response->assertDontSee('name="robots"', false);
    }
}
