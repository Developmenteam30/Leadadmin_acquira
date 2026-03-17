<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Get the authenticated user
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'idUser' => $user->idUser,
                'username' => $user->username,
                'fullName' => $user->fullName,
                'idCompany' => $user->idCompany,
                'accessBits' => $user->accessBits,
                'email' => $user->email,
                'emailBits' => $user->emailBits,
            ],
        ]);
    }
}
