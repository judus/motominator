<?php

namespace App\Models;

use App\Garage\Enums\MaintenanceActionType;
use App\Garage\Enums\MaintenanceRuleSource;
use App\Garage\Enums\MaintenanceScheduleKind;
use Database\Factories\MaintenanceTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MaintenanceActionType $type
 * @property MaintenanceScheduleKind $schedule_kind
 * @property MaintenanceRuleSource|null $source
 * @property Carbon|null $baseline_on
 * @property Carbon|null $target_on
 * @property int $id
 * @property int $maintenance_plan_id
 * @property string $title
 * @property string $component
 * @property string|null $position
 * @property int|null $interval_km
 * @property int|null $interval_months
 * @property int|null $target_odometer_km
 * @property int|null $baseline_odometer_km
 * @property string|null $source_reference
 * @property string|null $source_version
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MaintenancePlan $maintenancePlan
 * @property-read Collection<int, MaintenanceTaskOccurrence> $occurrences
 * @method static \Database\Factories\MaintenanceTaskFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(
    [
        'title',
        'type',
        'component',
        'position',
        'schedule_kind',
        'interval_km',
        'interval_months',
        'target_on',
        'target_odometer_km',
        'baseline_on',
        'baseline_odometer_km',
        'source',
        'source_reference',
        'source_version',
        'notes',
    ]
)]
class MaintenanceTask extends Model
{
    /** @use HasFactory<MaintenanceTaskFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => MaintenanceActionType::class,
            'schedule_kind' => MaintenanceScheduleKind::class,
            'source' => MaintenanceRuleSource::class,
            'interval_km' => 'integer',
            'interval_months' => 'integer',
            'target_on' => 'date',
            'target_odometer_km' => 'integer',
            'baseline_on' => 'date',
            'baseline_odometer_km' => 'integer'
        ];
    }

    /** @return BelongsTo<MaintenancePlan, $this> */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /** @return HasMany<MaintenanceTaskOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(MaintenanceTaskOccurrence::class);
    }
}
