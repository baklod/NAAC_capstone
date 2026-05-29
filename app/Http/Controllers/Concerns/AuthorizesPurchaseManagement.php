<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Exceptions\HttpResponseException;

trait AuthorizesPurchaseManagement
{
    protected function assertManagerOrAdmin(string $message = 'Unauthorized. Manager or admin role required.'): void
    {
        $user = auth()->user();

        if ($user && $user->role === 'staff') {
            throw new HttpResponseException(
                response()->json(['message' => $message], 403)
            );
        }
    }

    protected function assertAdminOnly(
        string $message = 'Administrator access required.',
    ): void {
        $user = auth()->user();

        if (!$user || $user->role !== 'admin') {
            throw new HttpResponseException(
                response()->json(['message' => $message], 403)
            );
        }
    }

    protected function assertCanApprove(): void
    {
        $this->assertAdminOnly(
            'Only administrators can approve or reject purchase orders.',
        );
    }
}
