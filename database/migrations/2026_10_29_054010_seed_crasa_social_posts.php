<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Eight of CRASA's own recent posts, read from this server on 29 October
 * 2026: five from x.com/CRASA_ORG and three from the CRASA Secretariat's
 * LinkedIn page. The X markup is what publish.twitter.com/oembed returned
 * for each post; the LinkedIn rows carry the public embed address.
 *
 * Facebook is not seeded: facebook.com sends a reader who is not signed in
 * to its login page, so no post address could be read from the CRASA page.
 *
 * Matched by URL, so running it twice adds nothing, and a post the
 * Secretariat has since edited or unpublished is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_posts')) {
            return;
        }

        $now = now();

        foreach ($this->rows() as $row) {
            if (DB::table('social_posts')->where('url', $row['url'])->exists()) {
                continue;
            }

            DB::table('social_posts')->insert($row + [
                'status' => 'published',
                'published_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('social_posts')) {
            DB::table('social_posts')->whereIn('url', array_column($this->rows(), 'url'))->delete();
        }
    }

    /** @return list<array<string, string|null>> */
    private function rows(): array
    {
        return json_decode(<<<'JSON'
[
    {
        "network": "x",
        "url": "https://x.com/CRASA_ORG/status/2113994250996064589",
        "posted_at": "2026-10-24 12:00:00",
        "caption": "Pantu ya Independence Day ku bakoeshiba nabo bonse aba ku ZICTA, na ku ba Zambia bonse! Mwaiseni bwino pa myaka 62 iya kwibuka ukwikala kwesu aba Zambia abacete!@ZictaZM @ZambiaPostal",
        "embed_html": "<blockquote class=\"twitter-tweet\" data-dnt=\"true\"><p lang=\"tl\" dir=\"ltr\">Pantu ya Independence Day ku bakoeshiba nabo bonse aba ku ZICTA, na ku ba Zambia bonse! Mwaiseni bwino pa myaka 62 iya kwibuka ukwikala kwesu aba Zambia abacete!<a href=\"https://x.com/ZictaZM?ref_src=twsrc%5Etfw\">@ZictaZM</a> <a href=\"https://x.com/ZambiaPostal?ref_src=twsrc%5Etfw\">@ZambiaPostal</a> <a href=\"https://t.co/MAIsT7n1BJ\">pic.twitter.com/MAIsT7n1BJ</a></p>- CRASA (@CRASA_ORG) <a href=\"https://x.com/CRASA_ORG/status/2113994250996064589?ref_src=twsrc%5Etfw\">October 24, 2026</a></blockquote>",
        "embed_url": null
    },
    {
        "network": "x",
        "url": "https://x.com/CRASA_ORG/status/2113581769001746817",
        "posted_at": "2026-10-23 12:00:00",
        "caption": "The Smart Africa–CRASA Workshop concluded in Gaborone, advancing discussions on regional roaming, regulatory alignment and the One Africa Network. Our sincere appreciation to Smart Africa and all stakeholders for their...",
        "embed_html": "<blockquote class=\"twitter-tweet\" data-dnt=\"true\"><p lang=\"en\" dir=\"ltr\">The Smart Africa–CRASA Workshop concluded in Gaborone, advancing discussions on regional roaming, regulatory alignment and the One Africa Network.<br><br>Our sincere appreciation to Smart Africa and all stakeholders for their valuable contributions towards a more connected Africa. <a href=\"https://t.co/3KWuDUXp5a\">pic.twitter.com/3KWuDUXp5a</a></p>- CRASA (@CRASA_ORG) <a href=\"https://x.com/CRASA_ORG/status/2113581769001746817?ref_src=twsrc%5Etfw\">October 23, 2026</a></blockquote>",
        "embed_url": null
    },
    {
        "network": "x",
        "url": "https://x.com/CRASA_ORG/status/2112817084170195291",
        "posted_at": "2026-10-21 12:00:00",
        "caption": "DAY 1 | ADVANCING REGIONAL ROAMING AND DIGITAL INTEGRATION ACROSS AFRICA The Smart Africa–CRASA Workshop on the Implementation of Sub-regional Roaming Agreements, Stakeholder Engagement and Knowledge Exchange on the One...",
        "embed_html": "<blockquote class=\"twitter-tweet\" data-dnt=\"true\"><p lang=\"en\" dir=\"ltr\">DAY 1 | ADVANCING REGIONAL ROAMING AND DIGITAL INTEGRATION ACROSS AFRICA<br><br>The Smart Africa–CRASA Workshop on the Implementation of Sub-regional Roaming Agreements, Stakeholder Engagement and Knowledge Exchange on the One Africa Network (OAN) commenced yesterday in Gaborone, BW. <a href=\"https://t.co/xdfhumyNe7\">pic.twitter.com/xdfhumyNe7</a></p>- CRASA (@CRASA_ORG) <a href=\"https://x.com/CRASA_ORG/status/2112817084170195291?ref_src=twsrc%5Etfw\">October 21, 2026</a></blockquote>",
        "embed_url": null
    },
    {
        "network": "x",
        "url": "https://x.com/CRASA_ORG/status/2112207099702993061",
        "posted_at": "2026-10-19 12:00:00",
        "caption": "Second Ordinary Meeting of CRASA’s Economic Regulation Committee (ERC) for 2026/27 is underway in Gaborone, Botswana. Members will join the Smart Africa–CRASA roaming workshop on 20–22 October before the Committee...",
        "embed_html": "<blockquote class=\"twitter-tweet\" data-dnt=\"true\"><p lang=\"en\" dir=\"ltr\">Second Ordinary Meeting of CRASA’s Economic Regulation Committee (ERC) for 2026/27 is underway in Gaborone, Botswana. <br>Members will join the Smart Africa–CRASA roaming workshop on 20–22 October before the Committee reconvenes on Friday, 23 October to conclude its deliberations. <a href=\"https://t.co/X77LB8fXmF\">pic.twitter.com/X77LB8fXmF</a></p>- CRASA (@CRASA_ORG) <a href=\"https://x.com/CRASA_ORG/status/2112207099702993061?ref_src=twsrc%5Etfw\">October 19, 2026</a></blockquote>",
        "embed_url": null
    },
    {
        "network": "x",
        "url": "https://x.com/CRASA_ORG/status/2074473025890599365",
        "posted_at": "2026-07-07 12:00:00",
        "caption": "Welcome to CRASA, Dr. Stanley Shanapinda, our new Executive Secretary. We look forward to your leadership as we continue advancing regional collaboration and digital transformation across Southern Africa.#CRASA...",
        "embed_html": "<blockquote class=\"twitter-tweet\" data-dnt=\"true\"><p lang=\"en\" dir=\"ltr\">Welcome to CRASA, Dr. Stanley Shanapinda, our new Executive Secretary.<br><br>We look forward to your leadership as we continue advancing regional collaboration and digital transformation across Southern Africa.<a href=\"https://x.com/hashtag/CRASA?src=hash&amp;ref_src=twsrc%5Etfw\">#CRASA</a> <a href=\"https://x.com/hashtag/Leadership?src=hash&amp;ref_src=twsrc%5Etfw\">#Leadership</a> <a href=\"https://x.com/hashtag/ICT?src=hash&amp;ref_src=twsrc%5Etfw\">#ICT</a> <a href=\"https://x.com/hashtag/SouthernAfrica?src=hash&amp;ref_src=twsrc%5Etfw\">#SouthernAfrica</a> <a href=\"https://t.co/E1jiMa90pc\">pic.twitter.com/E1jiMa90pc</a></p>- CRASA (@CRASA_ORG) <a href=\"https://x.com/CRASA_ORG/status/2074473025890599365?ref_src=twsrc%5Etfw\">July 7, 2026</a></blockquote>",
        "embed_url": null
    },
    {
        "network": "linkedin",
        "url": "https://www.linkedin.com/posts/crasa-secretariat-communications-regulators-association-of-southern-africa_an-important-step-towards-crasas-digital-activity-7521155978814185472-oY_W",
        "posted_at": "2026-10-28 10:28:41",
        "caption": "An important step towards CRASA’s digital transformation! The ongoing validation of CRASA’s new website.",
        "embed_html": null,
        "embed_url": "https://www.linkedin.com/embed/feed/update/urn:li:activity:7521155978814185472"
    },
    {
        "network": "linkedin",
        "url": "https://www.linkedin.com/posts/crasa-secretariat-communications-regulators-association-of-southern-africa_crasa-cto-bocra-activity-7520749065924505600-B4Kg",
        "posted_at": "2026-10-27 07:31:46",
        "caption": "CRASA advocates for inclusive, secure and resilient digital infrastructure at the CTO Executive Masterclass.",
        "embed_html": null,
        "embed_url": "https://www.linkedin.com/embed/feed/update/urn:li:activity:7520749065924505600"
    },
    {
        "network": "linkedin",
        "url": "https://www.linkedin.com/posts/crasa-secretariat-communications-regulators-association-of-southern-africa_crasa-smartafrica-sada-activity-7516468281613099008-BITy",
        "posted_at": "2026-10-15 12:01:27",
        "caption": "Strengthening collaboration between CRASA and Smart Africa.",
        "embed_html": null,
        "embed_url": "https://www.linkedin.com/embed/feed/update/urn:li:activity:7516468281613099008"
    }
]
JSON, true, 512, JSON_THROW_ON_ERROR);
    }
};
