<?php

namespace App\Accounts\Http\Controllers;

use App\Accounts\Actions\StartNativeSocialLink;
use App\Accounts\Actions\ConsumeNativeSocialLink;
use App\Http\Controllers\Controller;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NativeSocialLinkController extends Controller
{
    public function store(Request $request, StartNativeSocialLink $start): JsonResponse
    {
        $code = $start($this->authenticatedUser($request), Input::object($request->all()));
        $provider = $request->string('provider')->toString();

        return response()->json([
            'code' => $code,
            'url' => url("/auth/{$provider}/redirect") . '?' . http_build_query(['native_link' => $code]),
        ], 201);
    }

    public function complete(Request $request, ConsumeNativeSocialLink $consume): JsonResponse
    {
        $consume($this->authenticatedUser($request), Input::object($request->all()));

        return response()->json([], 200);
    }
}
