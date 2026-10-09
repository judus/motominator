<?php

namespace App\Accounts\Http\Controllers;

use App\Accounts\Actions\ApproveNativeAuth;
use App\Accounts\Actions\ExchangeNativeAuth;
use App\Accounts\Actions\StartNativeAuth;
use App\Http\Controllers\Controller;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class NativeSocialAuthController extends Controller
{
    public function store(Request $request, StartNativeAuth $start): JsonResponse
    {
        $code = $start(Input::object($request->all()));
        $provider = $request->string('provider')->toString();

        return response()->json([
            'code' => $code,
            'url' => url("/auth/{$provider}/redirect") . '?' . http_build_query(['native' => $code]),
        ], 201);
    }

    public function complete(Request $request, ApproveNativeAuth $approve): RedirectResponse
    {
        $code = $request->session()->get('native.code');
        $expectedUserId = $request->session()->get('native.user_id');
        abort_unless(is_string($code) && is_int($expectedUserId), 403);
        $fingerprint = $request->session()->get('native.security_fingerprint');
        abort_unless(is_string($fingerprint), 403);
        $approve($this->authenticatedUser($request), $expectedUserId, $code, $fingerprint);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(Config::string('auth.mobile_return_url') . '?' . http_build_query(['code' => $code]));
    }

    public function exchange(Request $request, ExchangeNativeAuth $exchange): JsonResponse
    {
        return response()->json($exchange(Input::object($request->all())), 201);
    }
}
