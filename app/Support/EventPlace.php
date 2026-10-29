<?php

namespace App\Support;

/**
 * Where a meeting's town is, for the small map on the event page.
 *
 * Only towns CRASA meetings are held in, from their public coordinates.
 * A place not on the list (a country alone, an unfamiliar town) gets no
 * map rather than a wrong one; the address is always shown as text.
 */
final class EventPlace
{
    /** town => [latitude, longitude] */
    private const TOWNS = [
        'gaborone' => [-24.6282, 25.9231],
        'lilongwe' => [-13.9626, 33.7741],
        'dar es salaam' => [-6.7924, 39.2083],
        'kinshasa' => [-4.4419, 15.2663],
        'windhoek' => [-22.5609, 17.0658],
        'maun' => [-19.9833, 23.4167],
        'johannesburg' => [-26.2041, 28.0473],
        'ezulwini' => [-26.4167, 31.1833],
        'lusaka' => [-15.3875, 28.3228],
        'harare' => [-17.8252, 31.0335],
        'maputo' => [-25.9692, 32.5732],
        'maseru' => [-29.3151, 27.4869],
        'mbabane' => [-26.3054, 31.1367],
        'luanda' => [-8.8390, 13.2894],
        'port louis' => [-20.1609, 57.5012],
        'moroni' => [-11.7172, 43.2473],
        'pretoria' => [-25.7479, 28.2293],
        'blantyre' => [-15.7861, 35.0058],
        'victoria falls' => [-17.9318, 25.8307],
        'livingstone' => [-17.8419, 25.8543],
        'swakopmund' => [-22.6784, 14.5266],
    ];

    /** @return array{lat: float, lng: float}|null */
    public static function coordinates(?string $location): ?array
    {
        if ($location === null) {
            return null;
        }

        $town = strtolower(trim(explode(',', $location)[0]));

        return isset(self::TOWNS[$town]) ? ['lat' => self::TOWNS[$town][0], 'lng' => self::TOWNS[$town][1]] : null;
    }

    /**
     * The tiles that cover a 768 by 384 pixel area centred on the place,
     * each with its offset, so a map is drawn with plain images.
     *
     * @return array<int, array{url: string, left: int, top: int}>
     */
    public static function tiles(float $lat, float $lng, int $zoom = 12): array
    {
        $n = 2 ** $zoom;
        $px = ($lng + 180) / 360 * $n * 256;
        $rad = deg2rad($lat);
        $py = (1 - log(tan($rad) + 1 / cos($rad)) / M_PI) / 2 * $n * 256;

        $left = $px - 384;
        $top = $py - 192;
        $template = (string) config('crasa.map.tile_url');
        $tiles = [];

        for ($tx = (int) floor($left / 256); $tx * 256 < $left + 768; $tx++) {
            for ($ty = (int) floor($top / 256); $ty * 256 < $top + 384; $ty++) {
                $tiles[] = [
                    'url' => str_replace(['{z}', '{x}', '{y}', '{s}'], [$zoom, $tx, $ty, 'a'], $template),
                    'left' => (int) round($tx * 256 - $left),
                    'top' => (int) round($ty * 256 - $top),
                ];
            }
        }

        return $tiles;
    }
}
