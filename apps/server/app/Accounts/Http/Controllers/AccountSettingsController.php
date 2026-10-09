<?php

namespace App\Accounts\Http\Controllers;

use App\Accounts\Actions\ConfirmAccountPassword;
use App\Accounts\Actions\Fortify\UpdateUserPassword;
use App\Accounts\Actions\Fortify\UpdateUserProfileInformation;
use App\Accounts\Actions\ManageAccountTwoFactor;
use App\Accounts\Actions\RevokeDeviceToken;
use App\Accounts\Actions\UnlinkSocialIdentity;
use App\Http\Controllers\Controller;
use App\Support\Input;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Sanctum\Contracts\HasAbilities;
use Laravel\Sanctum\PersonalAccessToken;

class AccountSettingsController extends Controller
{
    public function profile(Request $request, UpdateUserProfileInformation $update): JsonResponse
    {
        $update->update($this->authenticatedUser($request), Input::object($request->all()));

        return response()->json([], 200);
    }

    public function password(Request $request, UpdateUserPassword $update): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $update->update($actor, [
            'current_password' => Input::string($request->input('current_password'), 'current_password'),
            'password' => Input::string($request->input('password'), 'password'),
            'password_confirmation' => Input::string($request->input('password_confirmation'), 'password_confirmation'),
        ]);
        PasswordUpdatedViaController::dispatch($actor);

        return response()->json([], 200);
    }

    public function twoFactor(Request $request, ManageAccountTwoFactor $manage): JsonResponse
    {
        return response()->json($manage($this->authenticatedUser($request), Input::object($request->all())));
    }

    public function devices(Request $request): JsonResponse
    {
        $actor = $this->authenticatedUser($request);
        $current = $actor->currentAccessToken();

        return response()->json($actor->tokens()->get()->map(fn (PersonalAccessToken $token): array => [
            'id' => $token->id,
            'name' => $token->name,
            'created_at' => $token->created_at,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'current_device' => $this->currentDevice($current, $token->id),
        ]));
    }

    public function revokeDevice(
        Request $request,
        int $token,
        ConfirmAccountPassword $confirm,
        RevokeDeviceToken $revoke,
    ): JsonResponse {
        $actor = $this->authenticatedUser($request);
        $confirmedAt = $confirm($actor, Input::string($request->input('password'), 'password'));
        $current = $actor->currentAccessToken();
        $currentDevice = $this->currentDevice($current, $token);
        $revoke($actor, $token, $confirmedAt);

        return response()->json(['current_device' => $currentDevice]);
    }

    public function unlinkSocial(
        Request $request,
        string $provider,
        ConfirmAccountPassword $confirm,
        UnlinkSocialIdentity $unlink,
    ): JsonResponse {
        $actor = $this->authenticatedUser($request);
        $confirmedAt = $confirm($actor, Input::string($request->input('password'), 'password'));
        $unlink($actor, $provider, $confirmedAt);

        return response()->json([], 200);
    }

    public function verification(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([], 200);
    }

    private function currentDevice(HasAbilities $current, int $token): bool
    {
        return $current instanceof PersonalAccessToken && $current->id === $token;
    }
}
