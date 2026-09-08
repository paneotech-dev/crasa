<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    /*
     * Laravel 11 removed this from the base controller. Without it
     * $this->authorize() is undefined and every guarded action returns a 500
     * rather than a 403, which is a security check that silently is not one.
     */
    use AuthorizesRequests;
}
