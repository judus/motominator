<?php

namespace App\Models;

use Database\Factories\InvoiceImportFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property array<string, mixed>|null $draft
 * @property Carbon|null $started_at
 * @property int $id
 * @property int $user_id
 * @property int $motorcycle_id
 * @property string $path
 * @property string $filename
 * @property string $mime
 * @property int $size
 * @property string $sha256
 * @property string $status
 * @property int $version
 * @property string|null $attempt_id
 * @property string|null $error
 * @property string|null $provider
 * @property string|null $model
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $evidence_document_id
 * @property-read EvidenceDocument|null $evidenceDocument
 * @property-read MaintenanceInvoice|null $invoice
 * @property-read Motorcycle $motorcycle
 * @property-read User $user
 * @method static \Database\Factories\InvoiceImportFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Hidden(['path', 'sha256', 'attempt_id'])]
class InvoiceImport extends Model
{
    /** @use HasFactory<InvoiceImportFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['draft' => 'encrypted:array', 'started_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Motorcycle, $this> */
    public function motorcycle(): BelongsTo
    {
        return $this->belongsTo(Motorcycle::class);
    }

    /** @return HasOne<MaintenanceInvoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(MaintenanceInvoice::class);
    }

    /** @return BelongsTo<EvidenceDocument, $this> */
    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(EvidenceDocument::class, 'evidence_document_id');
    }
}
