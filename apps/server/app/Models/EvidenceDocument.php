<?php

namespace App\Models;

use App\Garage\Enums\EvidenceKind;
use Database\Factories\EvidenceDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EvidenceKind $kind
 * @property int $id
 * @property int $user_id
 * @property int|null $uploaded_by_id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, InvoiceImport> $invoiceImports
 * @property-read Collection<int, MaintenanceRecord> $maintenanceRecords
 * @property-read Collection<int, MileageReading> $mileageReadings
 * @property-read User|null $uploadedBy
 * @property-read User $user
 * @method static \Database\Factories\EvidenceDocumentFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(['kind', 'filename', 'mime', 'size'])]
#[Hidden(['disk', 'path', 'sha256'])]
class EvidenceDocument extends Model
{
    /** @use HasFactory<EvidenceDocumentFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => EvidenceKind::class, 'size' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    /** @return HasMany<InvoiceImport, $this> */
    public function invoiceImports(): HasMany
    {
        return $this->hasMany(InvoiceImport::class);
    }

    /** @return BelongsToMany<MaintenanceRecord, $this> */
    public function maintenanceRecords(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceRecord::class, 'maintenance_evidence')->withPivot(
            'attached_by_id'
        )->withTimestamps();
    }

    /** @return HasMany<MileageReading, $this> */
    public function mileageReadings(): HasMany
    {
        return $this->hasMany(MileageReading::class);
    }
}
