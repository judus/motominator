<?php

namespace App\Ai\Http\Controllers;

use App\Ai\Actions\RemoveAiSettings;
use App\Ai\Actions\SaveAiSettings;
use App\Ai\Actions\TestAiConnection;
use App\Http\Controllers\Controller;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class AiSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $credential = $this->authenticatedUser($request)->aiCredential()->first();

        return response()->json([
            'data' => $credential ? [
                'provider' => $credential->provider,
                'model' => $credential->model,
                'key_hint' => $credential->key_hint
            ] : null,
            'providers' => collect(
                Input::objects(Config::array('ai_byok.providers'), 'providers')
            )->map(
                fn (array $provider, int|string $id): array => ['id' => $id, 'label' => $provider['label']]
            )->values()->all(),
        ]);
    }

    public function update(Request $request, SaveAiSettings $save): JsonResponse
    {
        $save($this->authenticatedUser($request), Input::object(
            $request->only(['provider', 'model', 'api_key'])
        ));

        return $this->show($request);
    }

    public function destroy(Request $request, RemoveAiSettings $remove): JsonResponse
    {
        $remove($this->authenticatedUser($request));

        return $this->show($request);
    }

    public function test(Request $request, TestAiConnection $test): JsonResponse
    {
        $test($this->authenticatedUser($request));

        return response()->json(['message' => 'Connection successful. The selected model accepted a text request.']);
    }
}
