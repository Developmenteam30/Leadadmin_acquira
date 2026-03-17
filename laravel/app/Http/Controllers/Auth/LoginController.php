<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Find user by username
        $user = User::where('username', $request->username)
            ->where('isArchived', 0)
            ->first();

        // Verify password using PHP password_verify (for compatibility with existing hashes)
        if (!$user || !password_verify($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'username' => ['Invalid username or password.'],
            ]);
        }

        // Check if password needs rehashing (optional, for future compatibility)
        if (password_needs_rehash($user->password, PASSWORD_DEFAULT, ['cost' => 11])) {
            $user->password = password_hash($request->password, PASSWORD_DEFAULT, ['cost' => 11]);
            $user->save();
        }

        // Create Sanctum token
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 1,
            'error' => 'Successfully logged in.',
            'token' => $token,
            'user' => [
                'idUser' => $user->idUser,
                'username' => $user->username,
                'fullName' => $user->fullName,
                'idCompany' => $user->idCompany,
                'accessBits' => $user->accessBits,
                'email' => $user->email,
            ],
        ]);
    }
}
