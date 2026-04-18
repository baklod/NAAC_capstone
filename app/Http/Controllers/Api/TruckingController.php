<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trucking;
use Illuminate\Http\Request;

class TruckingController extends Controller
{
    public function index()
    {
        $trucking = Trucking::latest()->get();

        return response()->json(['data' => $trucking]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'driver_name' => ['required', 'string', 'max:255'],
            'delivery_address' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
        ]);

        $trucking = Trucking::create($validated);

        return response()->json([
            'message' => 'Trucking record created successfully.',
            'data' => $trucking,
        ], 201);
    }
}
