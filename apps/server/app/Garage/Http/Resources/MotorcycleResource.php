<?php

namespace App\Garage\Http\Resources;

use App\Models\Motorcycle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Motorcycle */
class MotorcycleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'nickname' => $this->nickname,
            'odometer_km' => $this->odometer_km
        ];
    }
}
