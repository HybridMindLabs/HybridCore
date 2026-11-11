<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Page sizes an admin list accepts from ?per_page=. */
    protected const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    protected function perPage(Request $request, int $default): int
    {
        $requested = (int) $request->query('per_page');

        return in_array($requested, self::PER_PAGE_OPTIONS, true) ? $requested : $default;
    }
}
