<?php

namespace App\Garage\Actions;

use App\Models\MaintenanceRecord;
use App\Models\MileageReading;
use App\Models\Motorcycle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ReadGarageContext
{
    private function currentActor(User $actor): User
    {
        $current = User::query()->findOrFail($actor->id);
        if (! $current->hasVerifiedEmail()) {
            throw new AuthorizationException();
        }

        return $current;
    }

    /** @return array<string, mixed> */
    public function motorcycles(User $actor, int $page): array
    {
        $actor = $this->currentActor($actor);
        $bikes = $actor->motorcycles()->orderBy('id')->paginate(20, ['*'], 'page', max(1, $page));

        return [
            'motorcycles' => $bikes->getCollection()->map($this->summary(...))->all(),
            'page' => $bikes->currentPage(),
            'last_page' => $bikes->lastPage(),
        ];
    }

    /**
     * @return array{
     *     id: int, make: string, model: string, year: int, nickname: string|null, odometer_km: int,
     *     maintenance_count: int, recent_maintenance: list<array<string, int|string|null>>,
     *     mileage_reading_count: int, recent_mileage_readings: list<array<string, int|string>>,
     *     history_limit: int, notes_limit_characters: int
     * }
     */
    public function motorcycle(User $actor, int $id): array
    {
        $actor = $this->currentActor($actor);
        $bike = $actor->motorcycles()->findOrFail($id);
        Gate::forUser($actor)->authorize('view', $bike);
        $maintenance = $bike->maintenanceRecords()->orderByDesc('performed_on')->orderByDesc('id')->limit(30)->get();
        $readings = $bike->mileageReadings()->orderByDesc('observed_on')->orderByDesc('id')->limit(30)->get();

        return $this->summary($bike) + [
            'maintenance_count' => $bike->maintenanceRecords()->count(),
            'recent_maintenance' => array_values($maintenance->map(fn (MaintenanceRecord $record): array => [
                'id' => $record->id,
                'performed_on' => $record->performed_on->toDateString(),
                'odometer_km' => $record->odometer_km,
                'title' => $record->title,
                'notes' => $record->notes === null ? null : mb_substr($record->notes, 0, 1500),
                'performer' => $record->performer->value,
                'origin' => $record->origin->value,
                'cost_amount' => $record->cost_amount,
                'currency' => $record->currency,
            ])->all()),
            'mileage_reading_count' => $bike->mileageReadings()->count(),
            'recent_mileage_readings' => array_values($readings->map(fn (MileageReading $reading): array => [
                'id' => $reading->id,
                'observed_on' => $reading->observed_on->toDateString(),
                'odometer_value' => $reading->odometer_value,
                'unit' => $reading->unit->value,
                'certainty' => $reading->certainty->value,
                'origin' => $reading->origin->value,
            ])->all()),
            'history_limit' => 30,
            'notes_limit_characters' => 1500,
        ];
    }

    /** @return array{id: int, make: string, model: string, year: int, nickname: string|null, odometer_km: int} */
    private function summary(Motorcycle $bike): array
    {
        return [
            'id' => $bike->id,
            'make' => $bike->make,
            'model' => $bike->model,
            'year' => $bike->year,
            'nickname' => $bike->nickname,
            'odometer_km' => $bike->odometer_km,
        ];
    }
}
