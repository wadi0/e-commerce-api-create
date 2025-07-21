<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    function login(Request $request)
    {

        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'error' => true,
                'msg' => 'User not found! Please sign up first.'
            ], 401);
        }

        $token = $user->createToken('MyApp')->plainTextToken;

        return response()->json([
            'error' => false,
            'success' => true,
            'result' => [
                'token' => $token,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'msg' => 'Login successful'
        ]);
    }

    function signup(Request $request)
    {
        try {
            $input = $request->all();

            // Optional: validate required fields
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
            ]);

            // $input['password'] = bcrypt($input['password']);

            // $user = User::create($input);
            $token = $user->createToken('MyApp')->plainTextToken;

            return response()->json([
                'error' => false,
                'success' => true,
                'result' => [
                    'token' => $token,
                    'name' => $user->name,
                ],
                'msg' => 'User registered successfully',
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation error
            return response()->json([
                'error' => true,
                'msg' => $e->errors(), // multiple validation errors
            ], 422);
        } catch (\Exception $e) {
            // General error
            return response()->json([
                'error' => true,
                'msg' => $e->getMessage(),
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'error' => false,
            'success' => true,
            'msg' => 'Logged out successfully',
        ]);
    }
}
