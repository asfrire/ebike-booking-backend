<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subdivision;
use App\Models\Phase;
use App\Models\Fare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FareController extends Controller
{
    public function index(Request $request)
    {
        $fares = Fare::with(['subdivision', 'phase'])
            ->orderBy('subdivision_id')
            ->orderBy('phase_id')
            ->get();

        return response()->json($fares);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdivision_id' => 'required|exists:subdivisions,id',
            'phase_id' => 'required|exists:phases,id',
            'fare_per_passenger' => 'required|numeric|min:0|max:99999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if fare already exists for this combination
        $existingFare = Fare::where('subdivision_id', $request->subdivision_id)
                           ->where('phase_id', $request->phase_id)
                           ->first();

        if ($existingFare) {
            return response()->json([
                'message' => 'Fare already exists for this subdivision and phase',
                'existing_fare' => $existingFare
            ], 422);
        }

        $fare = Fare::create([
            'subdivision_id' => $request->subdivision_id,
            'phase_id' => $request->phase_id,
            'fare_per_passenger' => $request->fare_per_passenger,
        ]);

        return response()->json([
            'message' => 'Fare created successfully',
            'fare' => $fare->load(['subdivision', 'phase'])
        ], 201);
    }

    public function show(Request $request, Fare $fare)
    {
        return response()->json($fare->load(['subdivision', 'phase']));
    }

    public function update(Request $request, Fare $fare)
    {
        $validator = Validator::make($request->all(), [
            'fare_per_passenger' => 'required|numeric|min:0|max:99999.99',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $fare->update([
            'fare_per_passenger' => $request->fare_per_passenger,
        ]);

        return response()->json([
            'message' => 'Fare updated successfully',
            'fare' => $fare->load(['subdivision', 'phase'])
        ]);
    }

    public function destroy(Request $request, Fare $fare)
    {
        $fare->delete();

        return response()->json(['message' => 'Fare deleted successfully']);
    }

    // Subdivision management
    public function subdivisions(Request $request)
    {
        $subdivisions = Subdivision::with('phases')->get();

        return response()->json($subdivisions);
    }

    public function storeSubdivision(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:subdivisions',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $subdivision = Subdivision::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Subdivision created successfully',
            'subdivision' => $subdivision
        ], 201);
    }

    // Phase management
    public function phases(Request $request)
    {
        $phases = Phase::with('subdivision')->get();

        return response()->json($phases);
    }

    public function storePhase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdivision_id' => 'required|exists:subdivisions,id',
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if phase already exists for this subdivision
        $existingPhase = Phase::where('subdivision_id', $request->subdivision_id)
                              ->where('name', $request->name)
                              ->first();

        if ($existingPhase) {
            return response()->json([
                'message' => 'Phase already exists for this subdivision',
                'existing_phase' => $existingPhase
            ], 422);
        }

        $phase = Phase::create([
            'subdivision_id' => $request->subdivision_id,
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Phase created successfully',
            'phase' => $phase->load('subdivision')
        ], 201);
    }

    // Fare calculation preview
    public function previewFare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdivision_id' => 'required|exists:subdivisions,id',
            'block_number' => 'required|string',
            'lot_number' => 'required|string',
            'pax' => 'required|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bookingService = app(\App\Services\BookingService::class);
        
        $result = $bookingService->processBookingWithFare([
            'subdivision_id' => $request->subdivision_id,
            'block_number' => $request->block_number,
            'lot_number' => $request->lot_number,
            'pax' => $request->pax,
        ]);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'phase' => $result['phase'],
            'fare_per_passenger' => $result['fare_per_passenger'],
            'total_fare' => $result['total_fare'],
            'formatted_fare_per_passenger' => '₱' . number_format($result['fare_per_passenger'], 2),
            'formatted_total_fare' => '₱' . number_format($result['total_fare'], 2),
        ]);
    }
}
