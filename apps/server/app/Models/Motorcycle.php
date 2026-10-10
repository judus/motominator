<?php

namespace App\Models;

use Database\Factories\MotorcycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $make
 * @property string $model
 * @property int $year
 * @property string|null $nickname
 * @property int $odometer_km
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceImport> $invoiceImports
 * @property-read Collection<int, MaintenancePlan> $maintenancePlans
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property-read Collection<int, MileageReading> $mileageReadings
 * @property-read User $user
 * @method static \Database\Factories\MotorcycleFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['make', 'model', 'year', 'nickname', 'odometer_km'])]
class Motorcycle extends Model
{
    /** @use HasFactory<MotorcycleFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<MaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['year' => 'integer', 'odometer_km' => 'integer'];
    }

    /** @return HasMany<MileageReading, $this> */
    public function mileageReadings(): HasMany
    {
        return $this->hasMany(MileageReading::class);
    }

    /** @return HasMany<MaintenancePlan, $this> */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /** @return HasMany<InvoiceImport, $this> */
    public function invoiceImports(): HasMany
    {
        return $this->hasMany(InvoiceImport::class);
    }
}
