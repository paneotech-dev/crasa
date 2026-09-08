<?php

namespace Tests\Fixtures;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\CountryScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * A stand-in for the country-owned models that arrive in later tasks:
 * submissions, indicator values, documents and extranet folders.
 *
 * No production model carries CountryScoped yet, and none should. Countries,
 * sectors, authorities and committees are all public reference data that
 * every role reads across all fourteen member states, so scoping them would
 * break the members page and the comparator. The scope exists for data a
 * member state owns, and this fixture proves the mechanism against a real
 * table and real queries until that data exists.
 */
class CountryRecord extends Model
{
    use Auditable, CountryScoped;

    protected $table = 'country_records';

    protected $fillable = ['country_id', 'title'];
}
