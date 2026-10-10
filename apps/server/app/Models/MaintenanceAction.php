<?php

namespace App\Models;

use App\Garage\Enums\MaintenanceActionType;
use Database\Factories\MaintenanceActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property MaintenanceActionType $type
 * @property int $id
 * @property int $maintenance_record_id
 * @property string $component
 * @property string|null $position
 * @property string|null $notes
 * @property string|null $findings
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, MaintenanceFulfilment> $fulfilments
 * @property-read MaintenanceRecord $maintenanceRecord
 * @method static \Database\Factories\MaintenanceActionFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['type', 'component', 'position', 'notes', 'findings'])]
class MaintenanceAction extends Model
{
    /** @use HasFactory<MaintenanceActionFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['type' => MaintenanceActionType::class];
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /** @return HasMany<MaintenanceFulfilment, $this> */
    public function fulfilments(): HasMany
    {
        return $this->hasMany(MaintenanceFulfilment::class);
    }
}
