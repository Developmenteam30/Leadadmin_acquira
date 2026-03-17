<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldController extends Controller
{
    /**
     * Get all fields
     */
    public function index()
    {
        try {
            $fields = Field::orderByRaw("REPLACE(fieldName, 'c_', '')")
                ->get()
                ->map(function ($field) {
                    return [
                        'fieldId' => $field->fieldId,
                        'fieldName' => $field->fieldName,
                        'fieldType' => $field->fieldType,
                        'fieldDescription' => $field->fieldDescription,
                        'fieldDefinition' => $field->fieldDefinition,
                        'fieldFormat' => $field->fieldFormat,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $fields,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch fields: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get a single field
     */
    public function show($id)
    {
        try {
            $field = Field::find($id);
            
            if (!$field) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Field not found',
                ], 404);
            }

            return response()->json([
                'status' => 1,
                'data' => [
                    'fieldId' => $field->fieldId,
                    'fieldName' => $field->fieldName,
                    'fieldType' => $field->fieldType,
                    'fieldDescription' => $field->fieldDescription,
                    'fieldDefinition' => $field->fieldDefinition,
                    'fieldFormat' => $field->fieldFormat,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new field
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'fieldName' => 'required|string|max:255',
                'fieldDescription' => 'required|string|max:255',
                'fieldFormat' => 'nullable|string|max:255',
            ]);

            $fieldName = trim($request->fieldName);
            
            // Field name validation
            if (empty($fieldName) || $fieldName === 'c_') {
                return response()->json([
                    'status' => 0,
                    'error' => 'Field name cannot be blank.',
                ], 422);
            }

            // Auto-prepend 'c_' if not present
            if (strpos($fieldName, 'c_') !== 0) {
                $fieldName = 'c_' . $fieldName;
            }

            // Validate field name format (only letters, numbers, underscores, dashes)
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $fieldName)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Field name may only contain letters, numbers, underscores, and/or dashes.',
                ], 422);
            }

            // Check for duplicate name
            $exists = Field::where('fieldName', $fieldName)
                ->where('fieldType', 'custom')
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That field name already exists in the database.',
                ], 422);
            }

            $field = Field::create([
                'fieldName' => $fieldName,
                'fieldType' => 'custom',
                'fieldDescription' => trim($request->fieldDescription),
                'fieldFormat' => !empty($request->fieldFormat) ? trim($request->fieldFormat) : null,
                'fieldDefinition' => 'varchar(255)', // Default value
            ]);

            // TODO: Add audit log entry
            // $leads->auditLog('FIELDS:ADD', $field->fieldId);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new field.',
                'data' => [
                    'fieldId' => $field->fieldId,
                    'fieldName' => $field->fieldName,
                    'fieldType' => $field->fieldType,
                    'fieldDescription' => $field->fieldDescription,
                    'fieldFormat' => $field->fieldFormat,
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
                'error' => 'Failed when trying to add a new field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a field
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'fieldDescription' => 'required|string|max:255',
                'fieldFormat' => 'nullable|string|max:255',
            ]);

            $field = Field::find($id);
            
            if (!$field) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Field not found',
                ], 404);
            }

            $field->fieldDescription = trim($request->fieldDescription);
            $field->fieldFormat = !empty($request->fieldFormat) ? trim($request->fieldFormat) : null;
            $field->save();

            // TODO: Add audit log entry
            // $leads->auditLog('FIELDS:EDIT', $field->fieldId);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully altered existing field.',
                'data' => [
                    'fieldId' => $field->fieldId,
                    'fieldName' => $field->fieldName,
                    'fieldType' => $field->fieldType,
                    'fieldDescription' => $field->fieldDescription,
                    'fieldFormat' => $field->fieldFormat,
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
                'error' => 'Failed when trying to edit a field: ' . $e->getMessage(),
            ], 500);
        }
    }
}
