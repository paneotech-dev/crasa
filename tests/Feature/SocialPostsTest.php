<?php

namespace Tests\Feature;

use App\Models\SocialPost;
use App\Models\User;
use App\Support\SocialEmbed;
use Database\Seeders\CountrySeeder;
use Database\Seeders\SectorSeeder;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Social posts (F5, S7): pasted in the back office, drawn on the home page
 * and in the newsroom, never as an empty frame.
 */
class SocialPostsTest extends TestCase
{
    use RefreshDatabase;

    private const X_URL = 'https://x.com/CRASA_ORG/status/2113581769001746817';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CountrySeeder::class);
        $this->seed(SectorSeeder::class);
        $this->seed(SiteSettingSeeder::class);
    }

    private function fakeX(): void
    {
        Http::fake([
            'publish.twitter.com/*' => Http::response([
                'author_name' => 'CRASA',
                'html' => '<blockquote class="twitter-tweet" data-dnt="true"><p lang="en" dir="ltr">The Smart Africa CRASA Workshop concluded in Gaborone.</p>&mdash; CRASA (@CRASA_ORG) <a href="https://x.com/CRASA_ORG/status/2113581769001746817">October 23, 2026</a></blockquote>'."\n".'<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>',
            ]),
        ]);
    }

    public function test_the_seed_holds_real_crasa_posts_with_their_embeds(): void
    {
        $posts = SocialPost::live()->get();

        $this->assertGreaterThanOrEqual(6, $posts->count());
        foreach ($posts as $post) {
            $this->assertTrue($post->hasEmbed(), $post->url);
            $this->assertMatchesRegularExpression('~^https://(x\.com/CRASA_ORG/status/\d+|www\.linkedin\.com/posts/crasa-secretariat-)~', $post->url);
        }
    }

    public function test_an_editor_adds_an_x_post_and_its_oembed_is_fetched_once_and_stored(): void
    {
        $this->fakeX();
        SocialPost::query()->delete();
        $editor = User::factory()->editor()->create();

        $this->actingAs($editor)->post('/en/admin/social-posts', ['url' => self::X_URL])
            ->assertRedirect();

        $post = SocialPost::firstWhere('url', self::X_URL);
        $this->assertSame('x', $post->network);
        $this->assertSame('draft', $post->status);
        $this->assertStringStartsWith('<blockquote class="twitter-tweet"', $post->embed_html);
        $this->assertStringNotContainsString('<script', $post->embed_html);
        $this->assertStringNotContainsString('&mdash;', $post->embed_html);
        $this->assertSame('2026-10-23', $post->posted_at->toDateString());
        Http::assertSentCount(1);

        // Drawing the post asks X nothing.
        $post->update(['status' => 'published']);
        $this->get('/en/newsroom')->assertOk()->assertSee('The Smart Africa CRASA Workshop concluded', false);
        Http::assertSentCount(1);
    }

    public function test_when_x_does_not_answer_the_post_is_saved_and_drawn_as_a_card(): void
    {
        Http::fake(['publish.twitter.com/*' => Http::response('', 503)]);
        SocialPost::query()->delete();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->post('/en/admin/social-posts', ['url' => self::X_URL, 'caption' => 'Workshop concluded'])
            ->assertSessionHas('status', __('fb.social_saved_no_embed'));

        $post = SocialPost::firstWhere('url', self::X_URL);
        $this->assertNull($post->embed_html);
        $post->update(['status' => 'published']);

        $html = $this->get('/en/newsroom')->getContent();
        $this->assertStringContainsString('s-post__caption', $html);
        $this->assertStringContainsString('Workshop concluded', $html);
        $this->assertStringContainsString('Open the post on X', $html);
    }

    public function test_facebook_and_linkedin_posts_get_their_frame_address_and_profiles_are_refused(): void
    {
        $fb = 'https://www.facebook.com/100064573337016/posts/pfbid02abcDEF';
        $this->assertSame('facebook', SocialEmbed::network($fb));
        $this->assertStringStartsWith('https://www.facebook.com/plugins/post.php?href=', SocialEmbed::frameUrl($fb));

        $li = 'https://www.linkedin.com/posts/crasa-secretariat_crasa-cto-bocra-activity-7520749065924505600-B4Kg';
        $this->assertSame('https://www.linkedin.com/embed/feed/update/urn:li:activity:7520749065924505600', SocialEmbed::frameUrl($li));

        $admin = User::factory()->administrator()->create();
        foreach (['https://x.com/CRASA_ORG', 'https://www.facebook.com/people/CRASA/100064573337016/', 'https://example.org/post/1'] as $bad) {
            $this->actingAs($admin)->from('/en/admin/social-posts/new')
                ->post('/en/admin/social-posts', ['url' => $bad])
                ->assertSessionHasErrors('url');
        }
    }

    public function test_only_content_people_reach_the_list_and_only_publishers_publish(): void
    {
        $post = SocialPost::first();

        $this->actingAs(User::factory()->viewer()->create())->get('/en/admin/social-posts')->assertForbidden();
        $this->actingAs(User::factory()->memberStateRep()->create())
            ->put("/en/admin/social-posts/{$post->id}/unpublish")->assertForbidden();

        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin)->get('/en/admin/social-posts')->assertOk()
            ->assertSee(__('fb.delete_confirm'), false);
        $this->actingAs($admin)->put("/en/admin/social-posts/{$post->id}/unpublish")->assertRedirect();
        $this->assertSame('draft', $post->fresh()->status);
    }

    public function test_delete_asks_first_and_removes_the_post(): void
    {
        $post = SocialPost::first();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get("/en/admin/social-posts/{$post->id}")->assertOk()
            ->assertSee('onsubmit="return confirm(', false);
        $this->actingAs($admin)->delete("/en/admin/social-posts/{$post->id}")->assertRedirect();
        $this->assertNull($post->fresh());
    }

    public function test_home_shows_the_three_most_recent_posts_in_one_row(): void
    {
        $html = view('partials.home.social')->render();

        $this->assertStringContainsString('CRASA on social media', $html);
        $this->assertSame(3, substr_count($html, '<article class="u-card s-post"'));
        $this->assertStringContainsString('s-posts--row', $html);
        $newest = SocialPost::live()->first();
        $this->assertStringContainsString(e($newest->embed_url ?: $newest->url), $html);
    }

    public function test_home_without_posts_shows_the_three_channel_cards_and_no_frame(): void
    {
        SocialPost::query()->update(['status' => 'draft']);

        $html = view('partials.home.social')->render();

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('s-post', $html);
        $this->assertSame(3, substr_count($html, 'class="u-card s-channel"'));
    }

    public function test_the_newsroom_feed_partial_lists_posts_newest_first_with_the_channels(): void
    {
        $html = view('partials.newsroom.social')->render();
        // An embedded post has no second link under it (item 8f): it is
        // found by its frame address, a post without an embed by its link.
        $urls = SocialPost::live()->get()->map(fn ($p) => e($p->embed_url ?: $p->url))->all();

        $positions = array_map(fn ($u) => strpos($html, $u), $urls);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
        $this->assertStringContainsString('s-channel', $html);
    }

    public function test_an_x_embed_keeps_only_the_tweet_text_and_links_and_drops_images_and_media(): void
    {
        $clean = SocialEmbed::cleanXHtml('<blockquote class="twitter-tweet" data-dnt="true" style="x"><p lang="en" dir="ltr" onclick="alert(1)">Spectrum workshop opens.<br><img src="https://evil.example/x.png"></p>'
            .'<link rel="stylesheet" href="https://evil.example/a.css"><base href="https://evil.example/"><meta http-equiv="refresh" content="0">'
            .'<video src="https://evil.example/v.mp4"><source src="https://evil.example/s.mp4"></video><picture><source srcset="https://evil.example/p.png"></picture>'
            .'<svg><image href="https://evil.example/i.png"/></svg><a href="javascript:alert(1)">bad</a> '
            .'&mdash; CRASA <a href="https://x.com/CRASA_ORG/status/2113581769001746817" target="_blank">October 23, 2026</a></blockquote>');

        $this->assertNotNull($clean);
        $this->assertStringNotContainsString('evil.example', $clean);
        foreach (['<img', '<link', '<base', '<meta', '<video', '<source', '<picture', '<svg', 'onclick', 'style=', 'javascript:', 'target='] as $gone) {
            $this->assertStringNotContainsString($gone, $clean, $gone);
        }
        $this->assertStringStartsWith('<blockquote class="twitter-tweet" data-dnt="true">', $clean);
        $this->assertStringContainsString('<p lang="en" dir="ltr">Spectrum workshop opens.<br>', $clean);
        $this->assertStringContainsString('<a href="https://x.com/CRASA_ORG/status/2113581769001746817">October 23, 2026</a>', $clean);

        // Nothing but markup left: nothing to show.
        $this->assertNull(SocialEmbed::cleanXHtml('<blockquote class="twitter-tweet"><img src="https://evil.example/x.png"></blockquote>'));
        $this->assertNull(SocialEmbed::cleanXHtml('<div><img src="https://evil.example/x.png"></div>'));

        // Drawn on the page from a stored value: the text and link, no image.
        SocialPost::query()->update(['status' => 'draft']);
        SocialPost::query()->where('url', self::X_URL)->delete();
        SocialPost::create([
            'network' => 'x', 'url' => self::X_URL, 'status' => 'published', 'posted_at' => now(),
            'embed_html' => '<blockquote class="twitter-tweet"><p lang="en" dir="ltr">Spectrum workshop opens.<img src="https://evil.example/x.png"></p><a href="'.self::X_URL.'">October 23, 2026</a></blockquote>',
        ]);
        $html = $this->get('/en/newsroom')->assertOk()->getContent();
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringContainsString('Spectrum workshop opens.', $html);
        $this->assertStringContainsString('<a href="'.self::X_URL.'">October 23, 2026</a>', $html);
    }
}
