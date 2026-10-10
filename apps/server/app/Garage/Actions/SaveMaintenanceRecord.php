<?php

namespace App\Garage\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Garage\Enums\HistoryOrigin;
use App\Models\MaintenanceRecord;
use App\Models\Motorcycle;
use App\Models\User;
use App\Support\Input;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SaveMaintenanceRecord
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function __invoke(
        User $actor,
        Motorcycle $motorcycle,
        array $input,
        ?MaintenanceRecord $record = null
    ): MaintenanceRecord {
        Gate::forUser($actor)->authorize('update', $motorcycle);
        if ($record && $record->motorcycle_id !== $motorcycle->id) {
            throw new ModelNotFoundException();
        }
        if (is_string($input['currency'] ?? null)) {
            $input['currency'] = strtoupper(trim($input['currency']));
        }
        $data = Input::object(Validator::make($input, [
            'performed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'odometer_km' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'title' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'cost_amount' => [
                'nullable',
                'required_with:currency',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],
            'currency' => ['nullable', 'required_with:cost_amount', 'regex:/^[A-Z]{3}$/'],
        ])->validate());
        $data += ['notes' => null, 'cost_amount' => null, 'currency' => null];

        return DB::transaction(function () use ($actor, $motorcycle, $record, $data): MaintenanceRecord {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            $locked = Motorcycle::query()->lockForUpdate()->findOrFail($motorcycle->id);
            Gate::forUser($actor)->authorize('update', $locked);
            if ($record) {
                $saved = $locked->maintenanceRecords()->lockForUpdate()->findOrFail($record->id);
                $before = clone $saved;
                $changed = $saved->fill($data)->isDirty();
                $saved->update($data);
            } else {
                $before = null;
                $changed = true;
                $saved = $locked->maintenanceRecords()->make($data);
                $saved->forceFill(['origin' => HistoryOrigin::Manual, 'recorded_by_id' => $actor->id])->save();
            }
            $this->recordUserActivity->maintenanceSaved($actor, $saved, $before, changed: $changed);
            if ($data['odometer_km'] > $locked->odometer_km) {
                $bikeBefore = clone $locked;
                $locked->update(['odometer_km' => $data['odometer_km']]);
                $this->recordUserActivity->motorcycleSaved($actor, $locked, $bikeBefore);
            }

            return $saved;
        });
    }
}
