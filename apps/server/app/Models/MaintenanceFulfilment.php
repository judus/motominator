<?php

namespace App\Models;

use Database\Factories\MaintenanceFulfilmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $maintenance_task_occurrence_id
 * @property int $maintenance_task_id
 * @property int $maintenance_action_id
 * @property int|null $confirmed_by_id
 * @property bool $completed
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $confirmedBy
 * @property-read MaintenanceAction $maintenanceAction
 * @property-read MaintenanceTask $maintenanceTask
 * @property-read MaintenanceTaskOccurrence $occurrence
 * @method static \Database\Factories\MaintenanceFulfilmentFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['completed', 'notes'])]
class MaintenanceFulfilment extends Model
{
    /** @use HasFactory<MaintenanceFulfilmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['completed' => 'boolean'];
    }

    /** @return BelongsTo<MaintenanceTaskOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTaskOccurrence::class, 'maintenance_task_occurrence_id');
    }

    /** @return BelongsTo<MaintenanceTask, $this> */
    public function maintenanceTask(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class);
    }

    /** @return BelongsTo<MaintenanceAction, $this> */
    public function maintenanceAction(): BelongsTo
    {
        return $this->belongsTo(MaintenanceAction::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id');
    }
}
