<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** Choices for "entries per page" in paginated lists. */
    public const PER_PAGE_OPTIONS = [10, 25, 100];

    public const PER_PAGE_DEFAULT = 25;

    /**
     * Entries per page for a list: from the query (and then remembered in the session),
     * else the remembered choice, else the default.
     */
    protected function perPage(Request $request, string $list): int
    {
        $key = "per_page.{$list}";
        $requested = (int) $request->query('per_page');

        if (in_array($requested, self::PER_PAGE_OPTIONS, true)) {
            $request->session()->put($key, $requested);

            return $requested;
        }

        $remembered = (int) $request->session()->get($key);

        return in_array($remembered, self::PER_PAGE_OPTIONS, true) ? $remembered : self::PER_PAGE_DEFAULT;
    }
}
