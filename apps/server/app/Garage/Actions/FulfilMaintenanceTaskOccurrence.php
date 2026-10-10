<?php

namespace App\Garage\Actions;

use App\Activity\Actions\RecordUserActivity;
use App\Activity\Enums\ActivityEvent;
use App\Garage\Enums\MaintenanceOccurrenceStatus;
use App\Garage\Exceptions\GarageMaintenanceException;
use App\Models\MaintenanceAction;
use App\Models\MaintenanceFulfilment;
use App\Models\MaintenanceTask;
use App\Models\MaintenanceTaskOccurrence;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FulfilMaintenanceTaskOccurrence
{
    public function __construct(
        private readonly RecordUserActivity $recordUserActivity,
    ) {
    }

    public function __invoke(
        User $actor,
        MaintenanceTaskOccurrence $occurrence,
        MaintenanceAction $action,
        bool $completed = true
    ): MaintenanceFulfilment {
        return DB::transaction(function () use ($actor, $occurrence, $action, $completed): MaintenanceFulfilment {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            // Serialize all occurrences of a task so one action cannot fulfil later cycles.
            $task = MaintenanceTask::query()->lockForUpdate()->findOrFail($occurrence->maintenance_task_id);
            $locked = $task->occurrences()->lockForUpdate()->findOrFail($occurrence->id);
            $work = MaintenanceAction::query()->lockForUpdate()->findOrFail($action->id);
            $record = $work->maintenanceRecord;
            Gate::forUser($actor)->authorize('update', $task->maintenancePlan->motorcycle);
            if (! ($record->motorcycle_id === $task->maintenancePlan->motorcycle_id)) {
                throw new ModelNotFoundException();
            }
            if ($locked->status === MaintenanceOccurrenceStatus::Skipped) {
                throw GarageMaintenanceException::occurrenceSkipped();
            }
            if (
                $task->type !== $work->type
                || $task->component !== $work->component
                || $task->position !== $work->position
            ) {
                throw ValidationException::withMessages(
                    ['action' => 'The action must match the task operation, component and position.']
                );
            }
            if ($locked->window_starts_on && $record->performed_on->lt($locked->window_starts_on)) {
                throw ValidationException::withMessages(['action' => 'This work predates the occurrence window.']);
            }
            $existing = MaintenanceFulfilment::query()->where('maintenance_task_id', $task->id)->where(
                'maintenance_action_id',
                $work->id
            )->first();
            if ($existing) {
                if (! ($existing->maintenance_task_occurrence_id === $locked->id)) {
                    throw GarageMaintenanceException::actionAlreadyUsed();
                }
                if ($existing->completed || ! $completed) {
                    return $existing;
                }
                $existing->forceFill(['completed' => true, 'confirmed_by_id' => $actor->id])->save();
                $fulfilment = $existing;
            } else {
                $fulfilment = new MaintenanceFulfilment();
                $fulfilment->forceFill([
                    'maintenance_task_id' => $task->id,
                    'maintenance_task_occurrence_id' => $locked->id,
                    'maintenance_action_id' => $work->id,
                    'confirmed_by_id' => $actor->id,
                    'completed' => $completed,
                ])->save();
            }
            if ($locked->status !== MaintenanceOccurrenceStatus::Completed) {
                $locked->update(
                    [
                        'status' => $completed
                        ? MaintenanceOccurrenceStatus::Completed
                        : MaintenanceOccurrenceStatus::Partial,
                    ]
                );
            }
            ($this->recordUserActivity)(
                $actor,
                $record->motorcycle->user_id,
                ActivityEvent::MaintenanceTaskFulfilled,
                $fulfilment->id,
                force: true
            );

            return $fulfilment;
        });
    }
}
