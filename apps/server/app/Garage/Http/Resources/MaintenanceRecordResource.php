<?php

namespace App\Garage\Http\Resources;

use App\Models\MaintenanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MaintenanceRecord */
class MaintenanceRecordResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'motorcycle_id' => $this->motorcycle_id,
            'performed_on' => $this->performed_on->format(
                'Y-m-d'
            ),
            'odometer_km' => $this->odometer_km,
            'title' => $this->title,
            'notes' => $this->notes,
            'cost_amount' => $this->cost_amount,
            'currency' => $this->currency
        ];
    }
}
