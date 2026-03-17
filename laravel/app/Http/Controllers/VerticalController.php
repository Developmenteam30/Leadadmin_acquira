<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Vertical;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VerticalController extends Controller
{
    /**
     * Get all divisions with their verticals
     */
    public function index()
    {
        try {
            $divisions = Division::orderBy('name')->get();
            
            $result = [];
            foreach ($divisions as $division) {
                $verticals = Vertical::where('divisionId', $division->divisionId)
                    ->orderBy('name')
                    ->get()
                    ->map(function ($vertical) {
                        return [
                            'verticalId' => $vertical->verticalId,
                            'name' => $vertical->name,
                        ];
                    })
                    ->toArray();

                $result[] = [
                    'divisionId' => $division->divisionId,
                    'name' => $division->name,
                    'verticals' => $verticals,
                ];
            }

            return response()->json([
                'status' => 1,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch verticals: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get a single vertical
     */
    public function show($id)
    {
        try {
            $vertical = Vertical::find($id);
            
            if (!$vertical) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Vertical not found',
                ], 404);
            }

            $division = Division::find($vertical->divisionId);

            return response()->json([
                'status' => 1,
                'data' => [
                    'verticalId' => $vertical->verticalId,
                    'name' => $vertical->name,
                    'divisionId' => $vertical->divisionId,
                    'divisionName' => $division ? $division->name : '',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch vertical: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all divisions for dropdown
     */
    public function getDivisions()
    {
        try {
            $divisions = Division::orderBy('name')->get()->map(function ($division) {
                return [
                    'divisionId' => $division->divisionId,
                    'name' => $division->name,
                ];
            });

            return response()->json([
                'status' => 1,
                'data' => $divisions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch divisions: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Create a new vertical
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'divisionId' => 'required|integer|exists:divisions,divisionId',
            ]);

            $name = trim($request->name);
            $divisionId = $request->divisionId;

            // Check for duplicate name in the same division
            $exists = Vertical::where('name', $name)
                ->where('divisionId', $divisionId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That vertical name already exists in this division.',
                ], 422);
            }

            $vertical = Vertical::create([
                'name' => $name,
                'divisionId' => $divisionId,
            ]);

            // TODO: Add audit log entry
            // $leads->auditLog('VERTICALS:ADD', $vertical->verticalId);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new vertical.',
                'data' => [
                    'verticalId' => $vertical->verticalId,
                    'name' => $vertical->name,
                    'divisionId' => $vertical->divisionId,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors);
            return response()->json([
                'status' => 0,
                'error' => is_array($firstError) ? $firstError[0] : 'Validation failed.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed when trying to add a new vertical: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a vertical
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $vertical = Vertical::find($id);
            
            if (!$vertical) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Vertical not found',
                ], 404);
            }

            $name = trim($request->name);

            // Check for duplicate name in the same division (excluding current vertical)
            $exists = Vertical::where('name', $name)
                ->where('divisionId', $vertical->divisionId)
                ->where('verticalId', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That vertical name already exists in this division.',
                ], 422);
            }

            $vertical->name = $name;
            $vertical->save();

            // TODO: Add audit log entry
            // $leads->auditLog('VERTICALS:EDIT', $vertical->verticalId);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully altered existing vertical.',
                'data' => [
                    'verticalId' => $vertical->verticalId,
                    'name' => $vertical->name,
                    'divisionId' => $vertical->divisionId,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $firstError = reset($errors);
            return response()->json([
                'status' => 0,
                'error' => is_array($firstError) ? $firstError[0] : 'Validation failed.',
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed when trying to edit a vertical: ' . $e->getMessage(),
            ], 500);
        }
    }
}
