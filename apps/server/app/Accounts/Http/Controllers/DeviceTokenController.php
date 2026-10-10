<?php

namespace App\Accounts\Http\Controllers;

use App\Accounts\Actions\AuthenticateDevice;
use App\Accounts\Actions\RevokeCurrentDeviceToken;
use App\Accounts\Actions\RevokeDeviceToken;
use App\Http\Controllers\Controller;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeviceTokenController extends Controller
{
    public function store(Request $request, AuthenticateDevice $authenticate): JsonResponse
    {
        $token = $authenticate(Input::object($request->all()));

        return $token === null
            ? response()->json(['two_factor' => true], 202)
            : response()->json($token, 201);
    }

    public function destroy(Request $request, RevokeCurrentDeviceToken $revoke): JsonResponse
    {
        abort_if(Auth::guard('web')->check(), 403, 'This endpoint requires a device token.');
        $actor = $this->authenticatedUser($request);
        $revoke($actor, $actor->currentAccessToken());

        return response()->json([], 200);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $this->authenticatedUser($request)->tokens()->get(
                ['id', 'name', 'created_at', 'last_used_at', 'expires_at']
            )
        );
    }

    public function revoke(Request $request, int $token, RevokeDeviceToken $revoke): JsonResponse
    {
        $revoke(
            $this->authenticatedUser($request),
            $token,
            Input::integer($request->session()->get('auth.password_confirmed_at', 0), 'confirmation'),
        );

        return response()->json([], 200);
    }
}
