<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\Request;

class DivisionController extends Controller
{
    /**
     * Create a new division
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:divisions,name',
            ]);

            $name = trim($request->name);

            // Check for duplicate name
            $exists = Division::where('name', $name)->exists();

            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That division name already exists in the database.',
                ], 422);
            }

            $division = Division::create([
                'name' => $name,
            ]);

            // TODO: Add audit log entry
            // $leads->auditLog('DIVISIONS:ADD', $division->divisionId);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new division.',
                'data' => [
                    'divisionId' => $division->divisionId,
                    'name' => $division->name,
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
                'error' => 'Failed when trying to add a new division: ' . $e->getMessage(),
            ], 500);
        }
    }
}
