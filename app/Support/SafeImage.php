<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use Illuminate\Http\UploadedFile;

/**
 * Is this upload a real image, judged by its content?
 *
 * Written for the authority logo (19 October 2026). The logo used to be
 * stored under the extension the uploader typed, on the public disk, so a
 * JPEG with a script appended and named logo.html was served as a page on
 * the site's own address. Now the type is read from the bytes, a raster
 * image must decode, and an SVG must be drawing and nothing else.
 *
 * THE SVG ALLOWLIST IS DELIBERATELY NARROW. A regulator's logo is shapes,
 * paths, gradients and text. Anything that can run or fetch is refused
 * outright rather than cleaned: script, event handlers, javascript: and
 * data: links, foreignObject (which embeds HTML), external references, and
 * a DOCTYPE (which can declare entities). A refused logo costs the member
 * state an email to the Secretariat; a cleaned one that was missed costs
 * every reader of the members page.
 */
final class SafeImage
{
    /** Detected MIME type => the extension the file is stored under. */
    public const TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    private const SVG_ELEMENTS = [
        'svg', 'g', 'defs', 'title', 'desc', 'metadata', 'path', 'rect', 'circle', 'ellipse', 'line',
        'polyline', 'polygon', 'text', 'tspan', 'textpath', 'lineargradient', 'radialgradient', 'stop',
        'clippath', 'mask', 'pattern', 'symbol', 'use', 'style', 'filter', 'fegaussianblur', 'feoffset',
        'feblend', 'fecolormatrix', 'feflood', 'fecomposite', 'femerge', 'femergenode',
    ];

    /** The extension to store a file under, or null when it is not a safe image. */
    public static function extensionFor(UploadedFile $file): ?string
    {
        $contents = (string) file_get_contents($file->getRealPath());

        // The type comes from the bytes, read here, not from the name and not
        // from whatever the upload layer guessed.
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';

        // libmagic calls an SVG without an XML declaration text/xml or plain
        // text; it is still only accepted if it parses as a safe drawing.
        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true)
            && stripos($contents, '<svg') !== false) {
            $mime = 'image/svg+xml';
        }

        $extension = self::TYPES[$mime] ?? null;

        if ($extension === null) {
            return null;
        }

        if ($extension === 'svg') {
            return self::svgIsSafe($contents) ? 'svg' : null;
        }

        // A raster image must actually decode as the type it claims.
        $info = @getimagesizefromstring($contents);

        return $info !== false && ($info['mime'] ?? null) === $mime ? $extension : null;
    }

    public static function svgIsSafe(string $svg): bool
    {
        if (stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
            return false;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || ! $document->documentElement instanceof DOMElement
            || strtolower($document->documentElement->localName) !== 'svg') {
            return false;
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (! in_array(strtolower($element->localName), self::SVG_ELEMENTS, true)) {
                return false;
            }

            if (strtolower($element->localName) === 'style' && preg_match('/@import|url\s*\(\s*["\']?\s*(?!#)/i', $element->textContent)) {
                return false;
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->localName);
                $value = strtolower(preg_replace('/\s+/', '', $attribute->value));

                if (str_starts_with($name, 'on')) {
                    return false;
                }

                // Links may only point inside the drawing itself.
                if (in_array($name, ['href', 'src'], true) && $value !== '' && ! str_starts_with($value, '#')) {
                    return false;
                }

                if (str_contains($value, 'javascript:') || preg_match('/url\((?!#)/', $value)) {
                    return false;
                }
            }
        }

        return true;
    }
}
