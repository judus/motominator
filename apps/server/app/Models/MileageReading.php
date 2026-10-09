<?php

namespace App\Models;

use App\Garage\Enums\HistoryOrigin;
use App\Garage\Enums\MileageUnit;
use App\Garage\Enums\ReadingCertainty;
use Database\Factories\MileageReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $observed_on
 * @property MileageUnit $unit
 * @property ReadingCertainty $certainty
 * @property HistoryOrigin $origin
 * @property int $id
 * @property int $motorcycle_id
 * @property int|null $recorded_by_id
 * @property int|null $maintenance_record_id
 * @property int|null $evidence_document_id
 * @property numeric-string $odometer_value
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EvidenceDocument|null $evidenceDocument
 * @property-read MaintenanceRecord|null $maintenanceRecord
 * @property-read Motorcycle $motorcycle
 * @property-read User|null $recordedBy
 * @method static \Database\Factories\MileageReadingFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['observed_on', 'odometer_value', 'unit', 'certainty', 'origin', 'notes'])]
class MileageReading extends Model
{
    /** @use HasFactory<MileageReadingFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'observed_on' => 'date',
            'odometer_value' => 'decimal:3',
            'unit' => MileageUnit::class,
            'certainty' => ReadingCertainty::class,
            'origin' => HistoryOrigin::class
        ];
    }

    /** @return BelongsTo<Motorcycle, $this> */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class);
    }
}
