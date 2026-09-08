<?php

namespace Tests\Fixtures;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for the translatable models that arrive in later stages:
 * instruments, publications, news items and page blocks. It exercises the
 * trait against a real table and real queries before those exist.
 */
class TranslatableRecord extends Model
{
    use HasTranslations;

    protected $table = 'translatable_records';

    protected $fillable = ['slug'];

    /** @var list<string> */
    protected array $translatable = ['title', 'summary', 'body'];
}
