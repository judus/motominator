<?php

namespace App\Models;

use App\Garage\Enums\MaintenanceOccurrenceStatus;
use Database\Factories\MaintenanceTaskOccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MaintenanceOccurrenceStatus $status
 * @property Carbon|null $window_starts_on
 * @property Carbon|null $due_on
 * @property int $id
 * @property int $maintenance_task_id
 * @property int|null $due_odometer_km
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MaintenanceFulfilment> $fulfilments
 * @property-read MaintenanceTask $maintenanceTask
 * @method static \Database\Factories\MaintenanceTaskOccurrenceFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['window_starts_on', 'due_on', 'due_odometer_km', 'status', 'notes'])]
class MaintenanceTaskOccurrence extends Model
{
    /** @use HasFactory<MaintenanceTaskOccurrenceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'window_starts_on' => 'date',
            'due_on' => 'date',
            'due_odometer_km' => 'integer',
            'status' => MaintenanceOccurrenceStatus::class
        ];
    }

    /** @return BelongsTo<MaintenanceTask, $this> */
    public function maintenanceTask(): BelongsTo
    {
        return $this->belongsTo(MaintenanceTask::class);
    }

    /** @return HasMany<MaintenanceFulfilment, $this> */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(MaintenanceFulfilment::class);
    }
}
