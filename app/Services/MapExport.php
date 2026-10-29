<?php

namespace App\Services;

use App\Support\MapView;
use App\Support\RegionGeometry;
use Imagick;
use RuntimeException;

/**
 * The shaded map as a document a regulator can put in a briefing.
 *
 * The comparator has carried PNG and PDF export since section 17; the map
 * did not, so a choropleth that would settle an agenda item had to be
 * screenshotted. This is the same pipeline: build a branded SVG, then let
 * Imagick with the Ghostscript delegate rasterise it. No headless browser
 * and no new dependency.
 *
 * The exported document carries what the screen carries and for the same
 * reasons: the mark, the dataset and its units, the period, every member
 * state's figure as text beside its shade, the "not reported" hatch, and
 * the permalink that reproduces the view. A figure in a ministerial brief
 * that cannot be traced back to the page it came from is a figure the
 * ministry cannot use.
 */
class MapExport
{
    public function png(MapView $view, string $permalink): string
    {
        return $this->rasterise($view, $permalink, 'png');
    }

    public function pdf(MapView $view, string $permalink): string
    {
        return $this->rasterise($view, $permalink, 'pdf');
    }

    /**
     * The map, drawn to the same geometry the page draws, on white, at a
     * print size.
     */
    public function svg(MapView $view, string $permalink): string
    {
        $map = config('region-map');
        $e = fn (?string $s) => htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');

        $width = 1240;
        $mapTop = 200;
        $mapScale = ($width - 80) / $map['width'];
        $mapHeight = (int) ($map['height'] * $mapScale);

        // Alphabetical, the order the page uses everywhere.
        $ordered = $view->countries->keys()->all();
        $rows = count($ordered);
        $tableTop = $mapTop + $mapHeight + 108;
        $height = $tableTop + ($rows * 26) + 140;

        $parts = [];
        $parts[] = '<rect width="'.$width.'" height="'.$height.'" fill="#FFFFFF"/>';

        // The hatch that means "not reported". A texture, not a fourth
        // green: "not reported" and "lowest value" are different claims
        // about a member state and must not look similar.
        $parts[] = '<defs><pattern id="hatch" width="8" height="8" patternUnits="userSpaceOnUse" patternTransform="rotate(135)">'
            .'<rect width="8" height="8" fill="#E5E0D5"/><rect width="4" height="8" fill="#CFC8B8"/></pattern></defs>';

        // Masthead, as the comparator's exports draw it.
        $parts[] = '<rect x="0" y="0" width="'.$width.'" height="96" fill="#0B5F3E"/>';
        $parts[] = $this->mark(40, 26);
        $parts[] = '<text x="104" y="46" font-family="Georgia,serif" font-size="24" font-weight="600" fill="#FFFFFF">'.$e(__('site.acronym')).'</text>';
        $parts[] = '<text x="104" y="70" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="#E5E0D5">'.$e(__('site.full_name')).'</text>';

        $title = $view->title() ?: __('members.map_option_none');
        $parts[] = '<text x="40" y="146" font-family="Georgia,serif" font-size="28" font-weight="600" fill="#0F1B2D">'.$e($title).'</text>';

        $subtitle = collect([$view->unit(), $view->period])->filter()->implode(', ');
        if ($subtitle !== '') {
            $parts[] = '<text x="40" y="172" font-family="Helvetica,Arial,sans-serif" font-size="14" fill="#5C6B7E">'.$e($subtitle).'</text>';
        }

        /*
         * The map itself, at the same geometry and the same band colours.
         *
         * The translate cancels the box's origin. That origin is 0,0 again
         * now the continent has gone, so the two terms fall away -- but the
         * arithmetic stays, because a box that can move is worth keeping
         * even when it is not moving, and the day it moved without this the
         * whole map landed down and to the right of its frame.
         */
        $originX = round(40 - ($mapScale * $map['min_x']), 4);
        $originY = round($mapTop - ($mapScale * $map['min_y']), 4);

        // Named, because the header logo is also a translated group and
        // anything looking for "the map" has to be able to tell them apart.
        $parts[] = '<g id="region" transform="translate('.$originX.' '.$originY.') scale('.round($mapScale, 4).')">';

        // Madagascar and Seychelles, SADC states that are not CRASA members:
        // outline only, as the page draws them, and named further down.
        foreach ($map['context'] as $context) {
            $parts[] = '<path d="'.$e($context['d']).'" fill="none" stroke="#7B8794" stroke-width="0.9"/>';

            if (($context['marker'] ?? null) !== null) {
                $parts[] = '<circle cx="'.$context['marker'][0].'" cy="'.$context['marker'][1].'" r="6" fill="none" stroke="#7B8794" stroke-width="1.2"/>';
            }
        }

        /*
         * Every shape, then every label. Not one country at a time.
         *
         * $ordered is alphabetical, so Lesotho is drawn before South Africa,
         * and Lesotho's label sits outside its own border because the
         * country is too small to hold it. South Africa's fill then landed
         * on top of it and the exported map went into a ministerial brief
         * showing thirteen named member states and one unnamed hole. The
         * page has always drawn the two passes separately; this is the
         * export catching up.
         */
        foreach ($ordered as $iso) {
            $shape = $map['members'][$iso] ?? null;

            if (($shape['d'] ?? null) === null) {
                continue;
            }

            $parts[] = '<path d="'.$e($shape['d']).'" fill="'.$this->fill($view, $view->entry($iso)?->bandAttribute())
                .'" stroke="#0E5536" stroke-width="1" stroke-linejoin="round"/>';
        }

        // Comoros and Mauritius as dots where they lie, as on the page. In
        // the shapes pass, so no fill lands on a label.
        foreach ($ordered as $iso) {
            $shape = $map['members'][$iso] ?? null;

            if (($shape['d'] ?? null) !== null || ($shape['dot'] ?? null) === null) {
                continue;
            }

            $parts[] = '<circle cx="'.$shape['dot'][0].'" cy="'.$shape['dot'][1].'" r="7" fill="'
                .($view->isShaded() ? $this->fill($view, $view->entry($iso)?->bandAttribute()) : '#0B5F3E')
                .'" stroke="#0E5536" stroke-width="1.5"/>';
        }

        foreach ($ordered as $iso) {
            $shape = $map['members'][$iso] ?? null;
            $entry = $view->entry($iso);

            if (($shape['dot_label'] ?? null) !== null && $entry !== null) {
                [$lx, $ly, $anchor] = $shape['dot_label'] + [2 => 'middle'];
                $parts[] = '<text x="'.$lx.'" y="'.$ly.'" text-anchor="'.$anchor.'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="700" fill="#0B5F3E">'
                    .$e($entry->country?->name ?: strtoupper($iso)).'</text>';
                if ($view->isShaded()) {
                    $parts[] = '<text x="'.$lx.'" y="'.($ly + 15).'" text-anchor="'.$anchor.'" font-family="Helvetica,Arial,sans-serif" font-size="11" font-weight="600" fill="#25384A">'
                        .$e($entry->display).'</text>';
                }
            }

            if (($shape['label'] ?? null) === null || $entry === null) {
                continue;
            }

            // Every state states its own figure. Colour is never the only
            // carrier, in a printed brief least of all.
            $ink = $view->isShaded() && in_array($entry->bandAttribute(), ['2', '3'], true) ? '#FFFFFF' : '#0F1B2D';
            /*
             * The country's name, not its ISO code, matching what the page
             * shows from 768 up. The export renders at 1160px, so it is
             * always at least that wide, and the reasoning is stronger
             * here than on screen: a printed brief goes to people who did
             * not choose to open it and cannot hover anything.
             *
             * Lesotho, Eswatini and Malawi keep the code, as they do on
             * the page. Their shapes cannot hold a name and each carries
             * its full name on an inset chip below the map.
             *
             * 13 and 11, the same two values the page carries.
             */
            $label = in_array($iso, ['ls', 'sz', 'mw'], true)
                ? strtoupper($iso)
                : ($entry->country?->name ?: strtoupper($iso));

            $parts[] = '<text x="'.$shape['label'][0].'" y="'.$shape['label'][1].'" text-anchor="middle" '
                .'font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="700" fill="'.$ink.'">'
                .$e($label).'</text>';
            $parts[] = '<text x="'.$shape['label'][0].'" y="'.($shape['label'][1] + 14).'" text-anchor="middle" '
                .'font-family="Helvetica,Arial,sans-serif" font-size="11" font-weight="600" fill="'.$ink.'">'
                .$e($entry->display).'</text>';
        }

        foreach ($map['context'] as $context) {
            $parts[] = '<text x="'.$context['lx'].'" y="'.$context['ly'].'" text-anchor="middle" font-family="Helvetica,Arial,sans-serif" font-size="13" font-style="italic" fill="#3F5264">'
                .$e(__('members.country_'.strtolower($context['code']))).'</text>';
        }

        $parts[] = '</g>';

        /*
         * The four smallest member states, enlarged, exactly as the page
         * draws them. Without this the exported map shows twelve shapes and
         * a reader counting them concludes CRASA has twelve members, which
         * is the one thing a membership map must never suggest.
         */
        $chipY = $mapTop + $mapHeight + 8;
        $chipX = 40;

        foreach (RegionGeometry::ENLARGED as $iso) {
            $shape = $map['members'][$iso] ?? null;
            $entry = $view->entry($iso);

            if ($shape === null || $entry === null) {
                continue;
            }

            $d = $shape['inset'] ?: $shape['d'];
            $box = $shape['inset'] ? '0 0 120 100' : RegionGeometry::viewBoxFor((string) $shape['d']);

            if ($d === null || $box === null) {
                continue;
            }

            $parts[] = '<svg x="'.$chipX.'" y="'.$chipY.'" width="44" height="33" viewBox="'.$e($box).'" preserveAspectRatio="xMidYMid meet">'
                .'<path d="'.$e($d).'" fill="'.$this->fill($view, $entry->bandAttribute())
                .'" stroke="#0E5536" stroke-width="1.2" stroke-linejoin="round"/></svg>';
            $parts[] = '<text x="'.($chipX + 52).'" y="'.($chipY + 15).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="#0F1B2D">'
                .$e(strtoupper($iso)).'</text>';
            $parts[] = '<text x="'.($chipX + 52).'" y="'.($chipY + 29).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="#1F2937">'
                .$e($entry->display).'</text>';

            $chipX += 150;
        }

        // Seychelles, the smallest SADC state, in outline: not a member.
        foreach ($map['context'] as $context) {
            if (($context['marker'] ?? null) === null) {
                continue;
            }

            $box = RegionGeometry::viewBoxFor($context['d'], 1.5);
            $parts[] = '<svg x="'.$chipX.'" y="'.$chipY.'" width="44" height="33" viewBox="'.$e($box).'" preserveAspectRatio="xMidYMid meet">'
                .'<path d="'.$e($context['d']).'" fill="none" stroke="#7B8794" stroke-width="0.2"/></svg>';
            $parts[] = '<text x="'.($chipX + 52).'" y="'.($chipY + 15).'" font-family="Helvetica,Arial,sans-serif" font-size="12" font-weight="700" fill="#3F5264">'
                .$e($context['code']).'</text>';
            $parts[] = '<text x="'.($chipX + 52).'" y="'.($chipY + 29).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="#3F5264">'
                .$e(__('members.country_'.strtolower($context['code']))).'</text>';
            $chipX += 150;
        }

        $parts[] = '<text x="40" y="'.($chipY + 56).'" font-family="Helvetica,Arial,sans-serif" font-size="11" font-style="italic" fill="#3F5264">'
            .$e(__('members.country_mg').', '.__('members.country_sc').': '.__('members.map_not_member')).'</text>';

        $parts[] = '<text x="40" y="'.($chipY - 8).'" font-family="Helvetica,Arial,sans-serif" font-size="11" fill="#5C6B7E">'
            .$e(__('members.map_enlarged')).'</text>';

        // The table, because four greens do not survive a photocopy and a
        // plenary paper is photocopied.
        $parts[] = '<line x1="40" y1="'.($tableTop - 22).'" x2="'.($width - 40).'" y2="'.($tableTop - 22).'" stroke="#E5E0D5" stroke-width="1"/>';

        foreach (array_values($ordered) as $index => $iso) {
            $entry = $view->entry($iso);
            $y = $tableTop + ($index * 26);

            $parts[] = '<text x="40" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" fill="#1F2937">'
                .$e($entry?->country->name).'</text>';
            $parts[] = '<text x="420" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="13" font-weight="600" fill="'
                .($entry?->reported ? '#0F1B2D' : '#5C6B7E').'">'.$e($entry?->display).'</text>';
            $parts[] = '<text x="640" y="'.$y.'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#5C6B7E">'
                .$e($entry?->latestPeriod ?: __('indicators.never')).'</text>';
        }

        // The permalink. A figure in a brief that cannot be traced back to
        // the page it came from is a figure the ministry cannot use.
        $parts[] = '<text x="40" y="'.($height - 56).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#5C6B7E">'
            .$e(__('comparator.csv_permalink')).'</text>';
        $parts[] = '<text x="40" y="'.($height - 38).'" font-family="Helvetica,Arial,sans-serif" font-size="12" fill="#0E5536">'
            .$e($permalink).'</text>';

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .implode('', $parts).'</svg>';
    }

    private function fill(MapView $view, ?string $band): string
    {
        if (! $view->isShaded()) {
            return '#E8F4EC';
        }

        return match ($band) {
            '0' => '#E8F4EC',
            '1' => '#9CCFB2',
            '2' => '#4FA97B',
            '3' => '#0E5536',
            default => 'url(#hatch)',
        };
    }

    private function rasterise(MapView $view, string $permalink, string $format): string
    {
        if (! extension_loaded('imagick')) {
            throw new RuntimeException('Imagick is required to export the map as '.$format.'.');
        }

        $image = new Imagick;
        $image->setBackgroundColor('white');
        $image->readImageBlob($this->svg($view, $permalink));
        $image->setImageFormat($format);

        if ($format === 'png') {
            $image->setImageResolution(144, 144);
        }

        $blob = $image->getImagesBlob();
        $image->clear();

        return $blob;
    }

    private function mark(int $x, int $y): string
    {
        $path = public_path('img/crasa-logo.svg');

        if (! is_readable($path)) {
            return '';
        }

        $svg = file_get_contents($path);
        $inner = preg_replace(['/^.*?<svg[^>]*>/s', '/<\/svg>.*$/s'], '', $svg);

        preg_match('/viewBox="([^"]+)"/', $svg, $box);
        $view = $box[1] ?? '0 0 48 48';
        [, , $w, $h] = array_map('floatval', preg_split('/[\s,]+/', $view));
        $scale = $h > 0 ? 44 / $h : 1;

        return '<g transform="translate('.$x.' '.$y.') scale('.round($scale, 4).')">'.$inner.'</g>';
    }
}
