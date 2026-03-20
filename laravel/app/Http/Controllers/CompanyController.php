<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Division;
use App\Models\Vertical;
use App\Models\User;
use App\Helpers\CompanyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    /**
     * Get all companies with optional filters
     */
    public function index(Request $request)
    {
        try {
            $status = $request->input('status');
            $searchText = $request->input('searchText');
            $searchAccountManager = $request->input('searchAccountManager');
            $searchAccountOpener = $request->input('searchAccountOpener');
            $searchCompanyType = $request->input('searchCompanyType');
            $searchSalesperson = $request->input('searchSalesperson');
            $searchDivisions = $request->input('searchDivisions', []);
            $searchVerticals = $request->input('searchVerticals', []);
            
            $query = Company::query();
            
            if ($status) {
                $query->where('status', $status);
            }
            
            if ($searchText) {
                $query->where(function ($q) use ($searchText) {
                    $q->where('name', 'LIKE', "%{$searchText}%")
                      ->orWhere('note', 'LIKE', "%{$searchText}%")
                      ->orWhere('url', 'LIKE', "%{$searchText}%");
                });
            }
            
            if ($searchAccountManager) {
                $query->where('accountManager', $searchAccountManager);
            }
            
            if ($searchAccountOpener) {
                $query->where('accountOpener', $searchAccountOpener);
            }
            
            if ($searchCompanyType) {
                if ($searchCompanyType === 'isPublisher') {
                    $query->where('isPublisher', true);
                } elseif ($searchCompanyType === 'isAdvertiser') {
                    $query->where('isAdvertiser', true);
                } elseif ($searchCompanyType === 'isCallCenter') {
                    $query->where('isCallCenter', true);
                }
            }
            
            if ($searchSalesperson) {
                $query->where('salesperson', $searchSalesperson);
            }
            
            if (!empty($searchDivisions)) {
                $query->whereHas('divisions', function ($q) use ($searchDivisions) {
                    $q->whereIn('divisions.divisionId', $searchDivisions);
                });
            }
            
            if (!empty($searchVerticals)) {
                $query->whereHas('verticals', function ($q) use ($searchVerticals) {
                    $q->whereIn('verticals.verticalId', $searchVerticals);
                });
            }

            CompanyScope::apply($query, $request->user(), 'idCompany');

            $companies = $query->orderBy('name')->get()->map(function ($company) {
                return [
                    'idCompany' => $company->idCompany,
                    'name' => $company->name,
                    'note' => $company->note,
                    'status' => $company->status,
                ];
            });

            return response()->json([
                'status' => 1,
                'data' => $companies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch companies: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get companies for dropdown (simplified)
     */
    public function getDropdown(Request $request)
    {
        try {
            $status = $request->input('status', 'active');
            $query = Company::orderBy('name');
            
            if ($status === 'active') {
                $query->where('status', 'active');
            } elseif ($status !== 'all') {
                $query->where('status', $status);
            }
            
            $companies = $query->get()->map(function ($company) {
                return [
                    'idCompany' => $company->idCompany,
                    'name' => $company->name,
                ];
            });

            return response()->json([
                'status' => 1,
                'data' => $companies,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch companies: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get a single company with divisions and verticals
     */
    public function show($id)
    {
        try {
            $company = Company::find($id);
            
            if (!$company) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Company not found',
                ], 404);
            }

            $divisions = $company->divisions()->pluck('divisions.divisionId')->toArray();
            $verticals = $company->verticals()->pluck('verticals.verticalId')->toArray();

            return response()->json([
                'status' => 1,
                'data' => [
                    'idCompany' => $company->idCompany,
                    'name' => $company->name,
                    'url' => $company->url,
                    'note' => $company->note,
                    'address' => $company->address,
                    'city' => $company->city,
                    'state' => $company->state,
                    'zipcode' => $company->zipcode,
                    'country' => $company->country,
                    'main_name' => $company->main_name,
                    'main_phone' => $company->main_phone,
                    'main_email' => $company->main_email,
                    'acct_name' => $company->acct_name,
                    'acct_phone' => $company->acct_phone,
                    'acct_email' => $company->acct_email,
                    'tech_name' => $company->tech_name,
                    'tech_phone' => $company->tech_phone,
                    'tech_email' => $company->tech_email,
                    'returns_name' => $company->returns_name,
                    'returns_phone' => $company->returns_phone,
                    'returns_email' => $company->returns_email,
                    'accountManager' => $company->accountManager,
                    'accountOpener' => $company->accountOpener,
                    'salesperson' => $company->salesperson,
                    'status' => $company->status,
                    'isPublisher' => $company->isPublisher,
                    'isAdvertiser' => $company->isAdvertiser,
                    'isCallCenter' => $company->isCallCenter,
                    'paymentTerms' => $company->paymentTerms,
                    'costPerLead' => $company->costPerLead,
                    'dialer_report_type' => $company->dialer_report_type,
                    'divisions' => $divisions,
                    'verticals' => $verticals,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get countries list
     */
    public function getCountries()
    {
        try {
            $countries = DB::table('countries')
                ->orderBy('short_name')
                ->get()
                ->map(function ($country) {
                    return [
                        'id' => $country->id,
                        'name' => $country->short_name,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $countries,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch countries: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Get staff users (salespersons)
     */
    public function getStaffUsers()
    {
        try {
            $users = User::whereRaw('(accessBits & ?) > 0', [0x200]) // LEADS_SESSION_LEVEL_SALESPERSON
                ->orderBy('username')
                ->get()
                ->map(function ($user) {
                    return [
                        'idUser' => $user->idUser,
                        'fullName' => $user->fullName,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch staff users: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Create a new company
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'url' => 'nullable|url|max:255',
                'main_email' => 'nullable|email|max:255',
                'returns_email' => 'nullable|email|max:255',
                'acct_email' => 'nullable|email|max:255',
                'tech_email' => 'nullable|email|max:255',
                'costPerLead' => 'nullable|numeric|min:0',
            ]);

            $name = trim($request->name);
            
            // Check for duplicate name (case-insensitive)
            $exists = Company::whereRaw('LOWER(name) = ?', [strtolower($name)])->exists();
            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That company name already exists in the database.',
                ], 422);
            }

            // Process company types
            $isPublisher = in_array('isPublisher', $request->input('companyType', []));
            $isAdvertiser = in_array('isAdvertiser', $request->input('companyType', []));
            $isCallCenter = in_array('isCallCenter', $request->input('companyType', []));

            $company = Company::create([
                'name' => $name,
                'url' => $request->url ? trim($request->url) : null,
                'note' => $request->note ? trim($request->note) : null,
                'address' => $request->address ? trim($request->address) : null,
                'city' => $request->city ? trim($request->city) : null,
                'state' => $request->state ? trim($request->state) : null,
                'zipcode' => $request->zipcode ? trim($request->zipcode) : null,
                'country' => $request->country ?? 236,
                'main_name' => $request->main_name ? trim($request->main_name) : null,
                'main_phone' => $request->main_phone ? trim($request->main_phone) : null,
                'main_email' => $request->main_email ? trim($request->main_email) : null,
                'returns_name' => $request->returns_name ? trim($request->returns_name) : null,
                'returns_phone' => $request->returns_phone ? trim($request->returns_phone) : null,
                'returns_email' => $request->returns_email ? trim($request->returns_email) : null,
                'acct_name' => $request->acct_name ? trim($request->acct_name) : null,
                'acct_phone' => $request->acct_phone ? trim($request->acct_phone) : null,
                'acct_email' => $request->acct_email ? trim($request->acct_email) : null,
                'tech_name' => $request->tech_name ? trim($request->tech_name) : null,
                'tech_phone' => $request->tech_phone ? trim($request->tech_phone) : null,
                'tech_email' => $request->tech_email ? trim($request->tech_email) : null,
                'accountManager' => $request->accountManager ? (int)$request->accountManager : null,
                'accountOpener' => $request->accountOpener ? (int)$request->accountOpener : null,
                'salesperson' => $request->salesperson ? (int)$request->salesperson : null,
                'isPublisher' => $isPublisher,
                'isAdvertiser' => $isAdvertiser,
                'isCallCenter' => $isCallCenter,
                'paymentTerms' => $request->paymentTerms ? trim($request->paymentTerms) : null,
                'costPerLead' => $request->costPerLead ? (float)$request->costPerLead : 0.00,
                'dialer_report_type' => $request->dialer_report_type ? trim($request->dialer_report_type) : null,
                'status' => 'active',
            ]);

            // Sync divisions
            if ($request->has('divisions') && is_array($request->divisions)) {
                $company->divisions()->sync($request->divisions);
            }

            // Sync verticals
            if ($request->has('verticals') && is_array($request->verticals)) {
                $company->verticals()->sync($request->verticals);
            }

            // TODO: Add audit log entry

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added new company.',
                'data' => [
                    'idCompany' => $company->idCompany,
                    'name' => $company->name,
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
                'error' => 'Failed when trying to add a new company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a company
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'status' => 'required|in:active,hidden,retired',
                'url' => 'nullable|url|max:255',
                'main_email' => 'nullable|email|max:255',
                'returns_email' => 'nullable|email|max:255',
                'acct_email' => 'nullable|email|max:255',
                'tech_email' => 'nullable|email|max:255',
                'costPerLead' => 'nullable|numeric|min:0',
            ]);

            $company = Company::find($id);
            
            if (!$company) {
                return response()->json([
                    'status' => 0,
                    'error' => 'Company not found',
                ], 404);
            }

            $name = trim($request->name);
            
            // Check for duplicate name (excluding current company, case-insensitive)
            $exists = Company::whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->where('idCompany', '!=', $id)
                ->exists();
            if ($exists) {
                return response()->json([
                    'status' => 0,
                    'error' => 'That company name already exists in the database.',
                ], 422);
            }

            // Process company types
            $isPublisher = in_array('isPublisher', $request->input('companyType', []));
            $isAdvertiser = in_array('isAdvertiser', $request->input('companyType', []));
            $isCallCenter = in_array('isCallCenter', $request->input('companyType', []));

            $company->name = $name;
            $company->url = $request->url ? trim($request->url) : null;
            $company->note = $request->note ? trim($request->note) : null;
            $company->address = $request->address ? trim($request->address) : null;
            $company->city = $request->city ? trim($request->city) : null;
            $company->state = $request->state ? trim($request->state) : null;
            $company->zipcode = $request->zipcode ? trim($request->zipcode) : null;
            $company->country = $request->country ?? 236;
            $company->main_name = $request->main_name ? trim($request->main_name) : null;
            $company->main_phone = $request->main_phone ? trim($request->main_phone) : null;
            $company->main_email = $request->main_email ? trim($request->main_email) : null;
            $company->returns_name = $request->returns_name ? trim($request->returns_name) : null;
            $company->returns_phone = $request->returns_phone ? trim($request->returns_phone) : null;
            $company->returns_email = $request->returns_email ? trim($request->returns_email) : null;
            $company->acct_name = $request->acct_name ? trim($request->acct_name) : null;
            $company->acct_phone = $request->acct_phone ? trim($request->acct_phone) : null;
            $company->acct_email = $request->acct_email ? trim($request->acct_email) : null;
            $company->tech_name = $request->tech_name ? trim($request->tech_name) : null;
            $company->tech_phone = $request->tech_phone ? trim($request->tech_phone) : null;
            $company->tech_email = $request->tech_email ? trim($request->tech_email) : null;
            $company->accountManager = $request->accountManager ? (int)$request->accountManager : null;
            $company->accountOpener = $request->accountOpener ? (int)$request->accountOpener : null;
            $company->salesperson = $request->salesperson ? (int)$request->salesperson : null;
            $company->status = $request->status;
            $company->isPublisher = $isPublisher;
            $company->isAdvertiser = $isAdvertiser;
            $company->isCallCenter = $isCallCenter;
            $company->paymentTerms = $request->paymentTerms ? trim($request->paymentTerms) : null;
            $company->costPerLead = $request->costPerLead ? (float)$request->costPerLead : 0.00;
            $company->dialer_report_type = $request->dialer_report_type ? trim($request->dialer_report_type) : null;
            $company->save();

            // Sync divisions
            if ($request->has('divisions')) {
                $company->divisions()->sync($request->divisions ?? []);
            }

            // Sync verticals
            if ($request->has('verticals')) {
                $company->verticals()->sync($request->verticals ?? []);
            }

            // TODO: Add audit log entry

            return response()->json([
                'status' => 1,
                'error' => 'Successfully altered existing company.',
                'data' => [
                    'idCompany' => $company->idCompany,
                    'name' => $company->name,
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
                'error' => 'Failed when trying to edit a company: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get company notes
     */
    public function getNotes($id)
    {
        try {
            $notes = DB::table('companies_notes')
                ->leftJoin('users', 'companies_notes.userId', '=', 'users.idUser')
                ->where('companies_notes.companyId', $id)
                ->orderBy('companies_notes.timestamp', 'desc')
                ->select(
                    'companies_notes.noteId',
                    'companies_notes.note',
                    'companies_notes.timestamp',
                    'users.fullName'
                )
                ->get()
                ->map(function ($note) {
                    return [
                        'noteId' => $note->noteId,
                        'note' => $note->note,
                        'timestamp' => $note->timestamp,
                        'fullName' => $note->fullName,
                    ];
                });

            return response()->json([
                'status' => 1,
                'data' => $notes,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Unable to fetch company notes: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    /**
     * Add a company note
     */
    public function addNote(Request $request, $id)
    {
        try {
            $request->validate([
                'note' => 'required|string',
            ]);

            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'status' => 0,
                    'error' => 'User not authenticated.',
                ], 401);
            }
            
            DB::table('companies_notes')->insert([
                'companyId' => $id,
                'userId' => $user->idUser,
                'timestamp' => DB::raw('NOW()'),
                'note' => trim($request->note),
            ]);

            return response()->json([
                'status' => 1,
                'error' => 'Successfully added note.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 0,
                'error' => 'Failed to add note: ' . $e->getMessage(),
            ], 500);
        }
    }
}
