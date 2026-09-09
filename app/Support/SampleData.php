<?php

namespace App\Support;

use App\Console\Commands\StripSampleData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * How much demonstration data is still in the database.
 *
 * The visible notices came off the public pages on 9 September 2026: sample
 * rows are flagged in the database and disclosed verbally by the presenter,
 * not badged on the page. That removes the reader's signal, so the
 * Secretariat needs one of their own, and this is it. The admin dashboard is
 * the only surface that carries it.
 *
 * It counts the same tables `content:strip-samples` deletes, from that
 * command's own list, so a model added to the strip is a model the dashboard
 * starts counting on the same commit rather than one that quietly stops
 * being reported.
 */
final class SampleData
{
    /**
     * Rows still flagged, by table label, highest first. Empty when clean.
     *
     * @return array<string, int>
     */
    public static function counts(): array
    {
        $counts = [];

        foreach (StripSampleData::ORDER as $class) {
            $count = self::query($class)->count();

            if ($count > 0) {
                $counts[class_basename($class)] = $count;
            }
        }

        arsort($counts);

        return $counts;
    }

    public static function total(): int
    {
        return array_sum(self::counts());
    }

    /**
     * Soft-deleted rows count. A row in the recycle bin is still a row in
     * the database, and go-live means the table is clean, not that the
     * listing looks clean.
     */
    private static function query(string $class)
    {
        /** @var Model $model */
        $model = new $class;

        $query = $model->newQuery()->where('is_sample', true);

        return in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $query->withTrashed()
            : $query;
    }
}
