<?php

namespace App\Models;

use Database\Factories\MaintenanceInvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $invoice_import_id
 * @property int $maintenance_record_id
 * @property int|null $workshop_id
 * @property string|null $invoice_number
 * @property numeric-string|null $subtotal_amount
 * @property numeric-string|null $tax_amount
 * @property numeric-string|null $total_amount
 * @property string|null $currency
 * @property int|null $labor_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read InvoiceImport $invoiceImport
 * @property-read Collection<int, MaintenanceInvoiceItem> $items
 * @property-read MaintenanceRecord $maintenanceRecord
 * @property-read Workshop|null $workshop
 * @method static \Database\Factories\MaintenanceInvoiceFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class MaintenanceInvoice extends Model
{
    /** @use HasFactory<MaintenanceInvoiceFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['subtotal_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total_amount' => 'decimal:2'];
    }

    /** @return HasMany<MaintenanceInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceInvoiceItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Workshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /** @return BelongsTo<InvoiceImport, $this> */
    public function invoiceImport(): BelongsTo
    {
        return $this->belongsTo(InvoiceImport::class);
    }
}
