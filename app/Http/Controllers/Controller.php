<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

abstract class Controller
{
    /**
     * Rend une liste. Quand la requête vient du filtre instantané (en-tête X-Partial),
     * seul le fragment du tableau est renvoyé et remplacé dans la page par Fetch.
     */
    protected function listView(Request $request, string $page, string $partial, array $data): View
    {
        return view($request->hasHeader('X-Partial') ? $partial : $page, $data);
    }

    protected function perPage(): int
    {
        return (int) config('stockflow.per_page', 20);
    }
}
