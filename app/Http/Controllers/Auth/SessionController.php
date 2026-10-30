<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return (new UserResource($request->user()))->response()->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Signed out successfully.',
            'csrf_token' => $request->session()->token(),
        ])->header('Cache-Control', 'no-store, private');
    }
}