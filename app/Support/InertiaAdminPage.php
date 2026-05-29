<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InertiaAdminPage
{
    public static function render(string $page, string $area): Response
    {
        $user = Auth::user();

        if ($user && $user->role === 'admin') {
            return Inertia::render($page);
        }

        return Inertia::render('Restricted', [
            'area' => $area,
            'role' => $user?->role ?? 'guest',
        ]);
    }
}
