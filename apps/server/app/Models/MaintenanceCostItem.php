<?php

namespace App\Models;

use Database\Factories\MaintenanceCostItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $maintenance_record_id
 * @property int|null $maintenance_invoice_item_id
 * @property int $position
 * @property string|null $description
 * @property numeric-string|null $quantity
 * @property numeric-string|null $unit_price
 * @property numeric-string|null $net_amount
 * @property numeric-string|null $tax_rate
 * @property numeric-string|null $tax_amount
 * @property numeric-string|null $total_amount
 * @property string|null $currency
 * @property int|null $labor_minutes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read MaintenanceInvoiceItem|null $invoiceItem
 * @property-read MaintenanceRecord $maintenanceRecord
 * @method static \Database\Factories\MaintenanceCostItemFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
#[Fillable(
    [
        'position',
        'description',
        'quantity',
        'unit_price',
        'net_amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'currency',
        'labor_minutes',
    ]
)]
class MaintenanceCostItem extends Model
{
    /** @use HasFactory<MaintenanceCostItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'net_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'labor_minutes' => 'integer'
        ];
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /** @return BelongsTo<MaintenanceInvoiceItem, $this> */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceInvoiceItem::class, 'maintenance_invoice_item_id');
    }
}
