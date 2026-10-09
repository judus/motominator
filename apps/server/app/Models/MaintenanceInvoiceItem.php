<?php

namespace App\Models;

use Database\Factories\MaintenanceInvoiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $maintenance_invoice_id
 * @property int $position
 * @property string|null $description
 * @property numeric-string|null $quantity
 * @property numeric-string|null $unit_price
 * @property numeric-string|null $net_amount
 * @property numeric-string|null $tax_rate
 * @property numeric-string|null $tax_amount
 * @property numeric-string|null $total_amount
 * @property int|null $labor_minutes
 * @property-read MaintenanceCostItem|null $costItem
 * @property-read MaintenanceInvoice $maintenanceInvoice
 * @method static \Database\Factories\MaintenanceInvoiceItemFactory factory($count = null, $state = [])
 * @mixin \Eloquent
 */
class MaintenanceInvoiceItem extends Model
{
    /** @use HasFactory<MaintenanceInvoiceItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'net_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2'
        ];
    }

    /** @return BelongsTo<MaintenanceInvoice, $this> */
    public function maintenanceInvoice(): BelongsTo
    {
        return $this->belongsTo(MaintenanceInvoice::class);
    }

    /** @return HasOne<MaintenanceCostItem, $this> */
    public function costItem(): HasOne
    {
        return $this->hasOne(MaintenanceCostItem::class);
    }
}
