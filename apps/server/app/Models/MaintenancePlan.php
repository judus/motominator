<?php

namespace App\Models;

use App\Garage\Enums\MaintenanceRuleSource;
use App\Garage\Enums\PlanStatus;
use Database\Factories\MaintenancePlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PlanStatus $status
 * @property MaintenanceRuleSource $source
 * @property Carbon|null $effective_on
 * @property int $id
 * @property int $motorcycle_id
 * @property int|null $created_by_id
 * @property string $name
 * @property string|null $notes
 * @property string|null $source_reference
 * @property string|null $source_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $createdBy
 * @property-read Motorcycle $motorcycle
 * @property-read Collection<int, MaintenanceTask> $tasks
 * @method static \Database\Factories\MaintenancePlanFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['name', 'notes', 'status', 'source', 'source_reference', 'source_version', 'effective_on'])]
class MaintenancePlan extends Model
{
    /** @use HasFactory<MaintenancePlanFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => PlanStatus::class, 'source' => MaintenanceRuleSource::class, 'effective_on' => 'date'];
    }

    /** @return BelongsTo<Motorcycle, $this> */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return HasMany<MaintenanceTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(MaintenanceTask::class);
    }
}
