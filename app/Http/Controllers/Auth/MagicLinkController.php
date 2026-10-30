<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Jobs\SendMagicLink;
use App\Services\MagicLinkService;
use App\Support\MagicLinkThrottle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    public function store(Request $request, MagicLinkThrottle $throttle): JsonResponse
    {
        $data = $request->validate([
            'email' => ['bail', 'required', 'string', 'email:rfc', 'max:255'],
        ]);
        $email = Str::lower(trim($data['email']));
        $throttle->forRequest($email, $request->ip());

        SendMagicLink::dispatch($email, now()->getTimestamp());

        return response()->json([
            'message' => 'If this email is registered, a sign-in link will be sent shortly.',
        ], 202)->header('Cache-Control', 'no-store, private');
    }

    public function verify(Request $request, MagicLinkService $links, MagicLinkThrottle $throttle): JsonResponse|RedirectResponse
    {
        $throttle->forVerification($request->ip());
        $data = $request->validate([
            'token' => ['bail', 'required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
        ]);
        $user = $links->consume($data['token']);

        Auth::login($user);
        $request->session()->regenerate();

        $response = $request->expectsJson()
            ? response()->json([
                'message' => 'Signed in successfully.',
                'user' => new UserResource($user),
                'csrf_token' => $request->session()->token(),
            ])
            : redirect()->route('home');

        return $response->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}