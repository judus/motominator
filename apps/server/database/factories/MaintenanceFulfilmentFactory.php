<?php

namespace Database\Factories;

use App\Models\MaintenanceAction;
use App\Models\MaintenanceFulfilment;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceFulfilment> */
class MaintenanceFulfilmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'maintenance_task_occurrence_id' => MaintenanceTaskOccurrence::factory(),
            'maintenance_task_id' => fn (array $attributes) => MaintenanceTaskOccurrence::query()->whereKey(
                $attributes['maintenance_task_occurrence_id']
            )->firstOrFail()->maintenance_task_id,
            'maintenance_action_id' => function (array $attributes): int {
                    $task = MaintenanceTask::query()->whereKey($attributes['maintenance_task_id'])->firstOrFail();
                    $record = MaintenanceRecord::factory()->create(
                        ['motorcycle_id' => $task->maintenancePlan->motorcycle_id]
                    );

                    return MaintenanceAction::factory()->create([
                        'maintenance_record_id' => $record->id,
                        'type' => $task->type,
                        'component' => $task->component,
                        'position' => $task->position,
                    ])->id;
            },
            'completed' => true,
        ];
    }
}
