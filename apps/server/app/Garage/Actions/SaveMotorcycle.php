<?php

namespace App\Garage\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Models\Motorcycle;
use App\Models\User;
use App\Support\Input;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveMotorcycle
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(User $actor, array $input, ?Motorcycle $motorcycle = null, ?User $owner = null): Motorcycle
    {
        Gate::forUser($actor)->authorize($motorcycle ? 'update' : 'create', $motorcycle ?? Motorcycle::class);
        $owner ??= $actor;
        if (! ($motorcycle || $actor->is_admin || $owner->is($actor))) {
            throw new AuthorizationException();
        }
        $data = Input::object(Validator::make($input, [
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1885', 'max:' . (now()->year + 1)],
            'nickname' => ['nullable', 'string', 'max:100'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ])->validate());
        $data += ['nickname' => null];

        return DB::transaction(function () use ($actor, $data, $motorcycle, $owner): Motorcycle {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $motorcycle) {
                Gate::forUser($actor)->authorize('create', Motorcycle::class);
                if (! ($actor->is_admin || $owner->is($actor))) {
                    throw new AuthorizationException();
                }
                $created = $owner->motorcycles()->create($data);
                $this->recordUserActivity->motorcycleSaved($actor, $created);

                return $created;
            }
            $locked = Motorcycle::query()->lockForUpdate()->findOrFail($motorcycle->id);
            Gate::forUser($actor)->authorize('update', $locked);
            if ($data['odometer_km'] < ($locked->maintenanceRecords()->max('odometer_km') ?? 0)) {
                throw ValidationException::withMessages(
                    ['odometer_km' => 'Mileage cannot be below a recorded maintenance reading.']
                );
            }
            $before = clone $locked;
            $locked->update($data);
            $this->recordUserActivity->motorcycleSaved($actor, $locked, $before);

            return $locked;
        });
    }
}
