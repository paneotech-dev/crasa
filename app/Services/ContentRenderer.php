<?php

namespace App\Services;

use App\Support\LegacyImages;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * The one place stored content becomes HTML. WS-12 and the writing rules.
 *
 * WHY THIS EXISTS. Three render sites did three different things to the
 * same kind of value. The newsroom escaped its HTML and printed the escaped
 * string, so a regulator read "&lt;p&gt;" on the page. The projects page
 * printed scraped third-party HTML raw and unsanitised. The events page
 * printed a WordPress builder's shortcodes. All three are the same missing
 * component: nothing turned a stored value into safe markup, so each
 * template improvised.
 *
 * ALLOWLIST, NOT BLOCKLIST. Only the tags below survive; everything else is
 * unwrapped and keeps its text. A blocklist is a list of the attacks
 * somebody thought of, and it is outrun by the first tag they did not. The
 * same rule governs attributes: four are kept and every other one is
 * dropped, which removes Webflow's class soup and any event handler without
 * naming either.
 *
 * IT IS A RENDERER, NOT A MIGRATION. The stored value is never changed.
 * That keeps the original for the Secretariat to consult, makes the whole
 * thing reversible by deleting one class, and means a later improvement
 * here fixes every record with no second pass over the database.
 *
 * IDEMPOTENT. Feeding the output back in produces the same output, which is
 * what makes it safe to call from a view that may render twice.
 */
class ContentRenderer
{
    /**
     * What survives.
     *
     * `sup` is here because a press release dates itself "the 31<sup>st</sup>
     * of July" and losing it turns a date into "31st" run together. `sub` is
     * here for symmetry; nothing uses it yet.
     *
     * @var list<string>
     */
    public const ALLOWED = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'sup', 'sub',
        'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote',
        'a', 'img', 'figure', 'figcaption',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    /**
     * Elements whose contents are dropped along with the element.
     *
     * Unwrapping a script would print its source as text on the page, which
     * is the fault this class exists to fix. Same for style.
     *
     * @var list<string>
     */
    private const DROP_WITH_CONTENTS = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'svg', 'form', 'input', 'button'];

    /** @var array<string, list<string>> */
    private const ATTRIBUTES = [
        'a' => ['href', 'title'],
        'img' => ['src', 'alt', 'title'],
        'th' => ['scope'],
    ];

    /**
     * Layout-only shortcodes: the wrapper goes, the text stays.
     *
     * The events host runs WordPress with a page builder, and its
     * description field is stored with the builder's markup in it.
     *
     * @var list<string>
     */
    private const SHORTCODES_UNWRAP = ['vc_row', 'vc_column', 'vc_row_inner', 'vc_column_inner', 'vc_column_text', 'vc_tta_section', 'vc_tta_tabs'];

    /** Shortcodes that carry nothing but spacing. @var list<string> */
    private const SHORTCODES_DROP = ['vc_empty_space', 'vc_separator'];

    public function __construct(private readonly LegacyImages $images) {}

    /**
     * Stored content, as markup a template may print.
     *
     * @param  string|null  $context  a record reference, for the log when an unknown shortcode turns up
     */
    public function render(?string $stored, ?string $context = null): HtmlString
    {
        $value = trim((string) $stored);

        if ($value === '') {
            return new HtmlString('');
        }

        /*
         * Trimmed after the shortcodes go, not only before. A value that
         * was "[vc_column_text] Text [/vc_column_text]" is " Text " at this
         * point, and leaving the spaces would mean rendering the output
         * again produced something different from the output.
         */
        $value = trim($this->shortcodes($value, $context));

        if ($value === '') {
            return new HtmlString('');
        }

        /*
         * Content with no tags at all is the ordinary case for everything
         * the Secretariat types. It gets the treatment it had before:
         * escaped, with its line breaks kept.
         */
        if (! preg_match('/<[a-zA-Z\/]/', $value)) {
            return new HtmlString(nl2br(e($value)));
        }

        return new HtmlString($this->sanitise($value));
    }

    /** The same content as plain text, for a meta description or a summary. */
    public function text(?string $stored): string
    {
        $html = $this->render($stored)->toHtml();

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * Page-builder shortcodes out, ordinary square brackets left alone.
     *
     * ONLY vc_-PREFIXED NAMES ARE TOUCHED. Two press releases carry a
     * dateline in square brackets -- "[Gaborone, 2nd June 2026]" -- and the
     * privacy and terms pages carry the bracketed notes that tell the
     * Secretariat what CRASA still has to supply. A stripper written to
     * "\[[a-z_]+\]" would delete all four and nobody would notice until a
     * reader asked where the dateline went.
     *
     * ATTRIBUTE QUOTES ARE NOT ALWAYS QUOTES. WordPress smart-quoted these
     * on the way in, so the stored text reads width="1/2" with a double
     * prime. The pattern below accepts every form rather than assuming one.
     */
    private function shortcodes(string $value, ?string $context): string
    {
        $name = '[a-z][a-z0-9_]*';
        $attributes = '(?:[^\]]*)';

        // Closing tags first, so an unwrapped pair leaves no orphan.
        $value = preg_replace_callback(
            '/\[\/('.$name.')\]/i',
            fn (array $m) => $this->isShortcode($m[1]) ? '' : $m[0],
            $value
        ) ?? $value;

        return preg_replace_callback(
            '/\[('.$name.')'.$attributes.'\]/i',
            function (array $m) use ($context): string {
                $tag = strtolower($m[1]);

                if (! $this->isShortcode($tag)) {
                    return $m[0];
                }

                if (in_array($tag, self::SHORTCODES_UNWRAP, true) || in_array($tag, self::SHORTCODES_DROP, true)) {
                    return '';
                }

                /*
                 * A builder tag nobody has ruled on. Its text is kept and
                 * the tag is recorded, so the next person knows what to
                 * add rather than discovering it on a page.
                 */
                Log::info('Unhandled shortcode in stored content.', ['shortcode' => $tag, 'record' => $context]);

                return '';
            },
            $value
        ) ?? $value;
    }

    private function isShortcode(string $tag): bool
    {
        return str_starts_with(strtolower($tag), 'vc_');
    }

    /** Parse, walk, keep what is allowed. */
    private function sanitise(string $html): string
    {
        $document = new DOMDocument;

        // Malformed markup is the ordinary case for scraped content, so the
        // parser's complaints are collected rather than raised.
        $previous = libxml_use_internal_errors(true);

        $loaded = $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="crasa-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            // Unparseable. Better a paragraph of the text than a page of
            // tags: this is the outcome the whole class exists to avoid.
            return nl2br(e(strip_tags($html)));
        }

        $root = $document->getElementById('crasa-root');

        if ($root === null) {
            return nl2br(e(strip_tags($html)));
        }

        $this->walk($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    private function walk(DOMNode $node): void
    {
        // Backwards, because unwrapping a child rewrites the live list.
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);

            if ($child instanceof DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // A comment, a processing instruction: not content.
                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->walk($child);

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::DROP_WITH_CONTENTS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            // The page already carries an h1. A second one breaks the
            // heading order for anybody navigating by headings.
            if ($tag === 'h1') {
                $this->rename($child, 'h2');

                continue;
            }

            if (! in_array($tag, self::ALLOWED, true)) {
                $this->unwrap($child);

                continue;
            }

            $this->attributes($child, $tag);

            if ($this->isEmpty($child)) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    /**
     * A paragraph that holds nothing a reader can see.
     *
     * Webflow ends a rich-text field with paragraphs containing only a
     * zero-width joiner, which render as blank gaps down the page. They are
     * an artefact of that editor rather than the author's spacing, and the
     * stored value keeps them either way.
     *
     * An element that legitimately carries no text -- an image, a line
     * break, a table cell -- is left alone.
     */
    private function isEmpty(DOMElement $element): bool
    {
        if (! in_array(strtolower($element->nodeName), ['p', 'li', 'blockquote', 'figcaption'], true)) {
            return false;
        }

        if ($element->getElementsByTagName('img')->length > 0) {
            return false;
        }

        $text = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', '', $element->textContent);

        return ($text ?? '') === '';
    }

    /** Replace an element with its children, keeping the text. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function rename(DOMElement $element, string $to): void
    {
        $replacement = $element->ownerDocument->createElement($to);

        while ($element->firstChild !== null) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);
    }

    /** Four attributes survive; the rest go, handlers and classes included. */
    private function attributes(DOMElement $element, string $tag): void
    {
        $allowed = self::ATTRIBUTES[$tag] ?? [];

        for ($i = $element->attributes->length - 1; $i >= 0; $i--) {
            $attribute = $element->attributes->item($i);
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a') {
            $this->link($element);
        }

        if ($tag === 'img') {
            $this->image($element);
        }
    }

    /**
     * A link that goes somewhere a reader expects.
     *
     * http, https, mailto and a same-page fragment. Anything else --
     * javascript:, data:, a scheme nobody has heard of -- loses its href
     * and becomes its own text, which keeps the words and removes the trap.
     */
    private function link(DOMElement $element): void
    {
        $href = trim($element->getAttribute('href'));
        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        $safe = str_starts_with($href, '#')
            || str_starts_with($href, '/')
            || in_array($scheme, ['http', 'https', 'mailto'], true);

        if (! $safe) {
            $this->unwrap($element);

            return;
        }

        if (in_array($scheme, ['http', 'https'], true)) {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * An image the platform actually holds.
     *
     * The stored bodies point at the old site's CDN. The platform's own
     * copy says every download comes from the platform itself, so a remote
     * source is resolved to the local file where one exists and the image
     * is dropped where one does not. Hot-linking somebody else's CDN from a
     * treaty body's site is a dependency on a host nobody here controls.
     *
     * alt is left as it was found, which is empty on all of them: Webflow
     * wrote alt="" and the editors never filled it in. Inventing a
     * description from a filename would be worse than none. Recorded in
     * docs/CLIENT_REQUESTS.md.
     */
    private function image(DOMElement $element): void
    {
        $local = $this->images->localUrl($element->getAttribute('src'));

        if ($local === null) {
            $element->parentNode?->removeChild($element);

            return;
        }

        $element->setAttribute('src', $local);
        $element->setAttribute('loading', 'lazy');
        $element->setAttribute('decoding', 'async');

        if (! $element->hasAttribute('alt')) {
            $element->setAttribute('alt', '');
        }
    }
}
