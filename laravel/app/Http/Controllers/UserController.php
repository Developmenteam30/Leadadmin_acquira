<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Company;
use App\Helpers\SessionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Get all users
     */
    public function index(Request $request)
    {
        try {
            $status = $request->input('status', 'active');
            
            $query = User::query();
            
            if ($status === 'active') {
                $query->where('isArchived', 0);
            } elseif ($status === 'archived') {
                $query->where('isArchived', 1);
            }
            // 'all' shows everything, no filter
            
            $users = $query->orderBy('username')->get()->map(function ($user) {
                // Get access level descriptions
                $accessLevels = [];
                $accessBits = SessionHelper::getAccessBits();
                foreach ($accessBits as $bit => $desc) {
                    if (SessionHelper::checkBit($user->accessBits, $bit)) {
                        $accessLevels[] = $desc;
                    }
                }
                
                // Get email notification descriptions
                $emailNotifications = [];
                $emailBits = SessionHelper::getEmailBits();
                foreach ($emailBits as $bit => $desc) {
                    if (SessionHelper::checkBit($user->emailBits, $bit)) {
                        $emailNotifications[] = $desc;
                    }
                }
                
                // Get company name
                $companyName = null;
                if ($user->idCompany) {
                    $company = Company::find($user->idCompany);
                    if ($company) {
                        $companyName = $company->name . ' (' . $company->idCompany . ')';
                    }
                }
                
                return [
                    'idUser' => $user->idUser,
                    'username' => $user->username,
                    'fullName' => $user->fullName,
                    'email' => $user->email,
                    'accessBits' => $user->accessBits,
                    'emailBits' => $user->emailBits,
                    'idCompany' => $user->idCompany,
                    'companyName' => $companyName,
                    'isArchived' => $user->isArchived,
                    'accessLevels' => $accessLevels,
                    'emailNotifications' => $emailNotifications,
                ];
            });

            return response()->json([
                'status' => 1,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch users: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get a single user
     */
    public function show($id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'error' => 'User not found',
                ], 404);
            }

            return response()->json([
                'status' => 1,
                'data' => [
                    'idUser' => $user->idUser,
                    'username' => $user->username,
                    'fullName' => $user->fullName,
                    'email' => $user->email,
                    'accessBits' => $user->accessBits,
                    'emailBits' => $user->emailBits,
                    'idCompany' => $user->idCompany,
                    'isArchived' => $user->isArchived,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get access bits and email bits options
     */
    public function getBits()
    {
        return response()->json([
            'status' => 1,
            'data' => [
                'accessBits' => SessionHelper::getAccessBits(),
                'emailBits' => SessionHelper::getEmailBits(),
            ],
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'fullName' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'accessBits' => 'required|array',
                'emailBits' => 'nullable|array',
                'idCompany' => 'nullable|integer|exists:companies,idCompany',
            ]);

            $username = strtolower(trim($request->username));
            
            // Validate username (alphanumeric only)
            if (!ctype_alnum($username)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'The username may only contain alphanumeric characters.',
                ], 422);
            }

            // Check for duplicate username
            $exists = User::where('username', $username)->exists();
            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Username already exists in the database.',
                ], 422);
            }

            // Calculate access bits
            $accessBits = SessionHelper::calculateBits($request->accessBits);
            $emailBits = SessionHelper::calculateBits($request->emailBits ?? []);

            // Validate company requirement for certain access levels
            $requiresCompany = SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_REPORTS) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_DASHBOARD) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CALL_CENTER);

            if ($requiresCompany && empty($request->idCompany)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Please associate this user with a company.',
                ], 422);
            }

            // Do not set company for CRM users and higher
            $isClientLevel = SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_REPORTS) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_DASHBOARD) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CALL_CENTER);

            $idCompany = $isClientLevel ? ($request->idCompany ?? null) : null;

            // Hash password using PHP password_hash
            $hashedPassword = password_hash($request->password, PASSWORD_DEFAULT, ['cost' => 11]);

            $user = User::create([
                'username' => $username,
                'password' => $hashedPassword,
                'fullName' => !empty($request->fullName) ? trim($request->fullName) : null,
                'idCompany' => $idCompany,
                'accessBits' => $accessBits,
                'emailBits' => $emailBits,
                'email' => !empty($request->email) ? trim($request->email) : null,
                'level' => 0,
                'isArchived' => 0,
            ]);

            // TODO: Add audit log entry
            // TODO: Send email notification

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new user.',
                'data' => [
                    'idUser' => $user->idUser,
                    'username' => $user->username,
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
                'error' => 'Failed when trying to add a new user: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a user
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'password' => 'nullable|string|min:8',
                'fullName' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'accessBits' => 'required|array',
                'emailBits' => 'nullable|array',
                'idCompany' => 'nullable|integer|exists:companies,idCompany',
                'isArchived' => 'nullable|boolean',
            ]);

            $user = User::find($id);
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Cannot find that userId in the database.',
                ], 404);
            }

            // Calculate access bits
            $accessBits = SessionHelper::calculateBits($request->accessBits);
            $emailBits = SessionHelper::calculateBits($request->emailBits ?? []);

            // Validate company requirement
            $requiresCompany = SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_REPORTS) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_DASHBOARD) ||
                              SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CALL_CENTER);

            if ($requiresCompany && empty($request->idCompany)) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Please associate this user with a company.',
                ], 422);
            }

            // Do not set company for CRM users and higher
            $isClientLevel = SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_PHONE_LEADS) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_REPORTS) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CLIENT_DASHBOARD) ||
                            SessionHelper::checkBit($accessBits, SessionHelper::LEADS_SESSION_LEVEL_CALL_CENTER);

            $idCompany = $isClientLevel ? ($request->idCompany ?? null) : null;

            // Update password if provided
            if (!empty($request->password)) {
                $hashedPassword = password_hash($request->password, PASSWORD_DEFAULT, ['cost' => 11]);
                $user->password = $hashedPassword;
            }

            // Handle archiving
            $isArchived = $request->input('isArchived', false) ? 1 : 0;
            if ($isArchived) {
                $accessBits = 0;
                $emailBits = 0;
            }

            $user->fullName = !empty($request->fullName) ? trim($request->fullName) : null;
            $user->idCompany = $idCompany;
            $user->accessBits = $accessBits;
            $user->emailBits = $emailBits;
            $user->email = !empty($request->email) ? trim($request->email) : null;
            $user->isArchived = $isArchived;
            $user->save();

            // TODO: Add audit log entry

            return response()->json([
                'status' => 1,
                'error' => 'Successfully edit user account.',
                'data' => [
                    'idUser' => $user->idUser,
                    'username' => $user->username,
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
                'error' => 'Failed when trying to edit user: ' . $e->getMessage(),
            ], 500);
        }
    }
}
