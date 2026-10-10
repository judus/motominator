<?php

namespace App\Models;

use App\Garage\Enums\HistoryOrigin;
use App\Garage\Enums\MaintenancePerformer;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $performed_on
 * @property HistoryOrigin $origin
 * @property MaintenancePerformer $performer
 * @property int $id
 * @property int $motorcycle_id
 * @property int $odometer_km
 * @property string $title
 * @property string|null $notes
 * @property numeric-string|null $cost_amount
 * @property string|null $currency
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $performer_name
 * @property int|null $workshop_id
 * @property int|null $recorded_by_id
 * @property int|null $labor_minutes
 * @property-read Collection<int, MaintenanceAction> $actions
 * @property-read Collection<int, MaintenanceCostItem> $costItems
 * @property-read Collection<int, EvidenceDocument> $evidenceDocuments
 * @property-read Collection<int, MileageReading> $mileageReadings
 * @property-read Motorcycle $motorcycle
 * @property-read User|null $recordedBy
 * @property-read Workshop|null $workshop
 * @method static \Database\Factories\MaintenanceRecordFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['performed_on', 'odometer_km', 'title', 'notes', 'cost_amount', 'currency'])]
class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory;

    /** @return BelongsTo<Motorcycle, $this> */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'odometer_km' => 'integer',
            'cost_amount' => 'decimal:2',
            'origin' => HistoryOrigin::class,
            'performer' => MaintenancePerformer::class,
            'labor_minutes' => 'integer'
        ];
    }

    /** @return HasMany<MaintenanceAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(MaintenanceAction::class);
    }

    /** @return HasMany<MaintenanceCostItem, $this> */
    public function costItems(): HasMany
    {
        return $this->hasMany(MaintenanceCostItem::class);
    }

    /** @return HasMany<MileageReading, $this> */
    public function mileageReadings(): HasMany
    {
        return $this->hasMany(MileageReading::class);
    }

    /** @return BelongsTo<Workshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /** @return BelongsToMany<EvidenceDocument, $this> */
    public function evidenceDocuments(): BelongsToMany
    {
        return $this->belongsToMany(EvidenceDocument::class, 'maintenance_evidence')->withPivot(
            'attached_by_id'
        )->withTimestamps();
    }
}
