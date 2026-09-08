<?php

namespace Tests\Fixtures;

use App\Models\Concerns\CountryScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * The same, for a table where a null country means regional and is visible
 * to every member state. Publications are the case that will need it.
 */
class SharedCountryRecord extends Model
{
    use CountryScoped;

    protected $table = 'country_records';

    protected $fillable = ['country_id', 'title'];

    public function countryScopeIncludesShared(): bool
    {
        return true;
    }
}
