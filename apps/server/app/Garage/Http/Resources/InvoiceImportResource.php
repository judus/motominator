<?php

namespace App\Garage\Http\Resources;

use App\Models\InvoiceImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvoiceImport */
class InvoiceImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'motorcycle_id' => $this->motorcycle_id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'status' => $this->status,
            'version' => $this->version,
            'draft' => $this->draft,
            'error' => $this->error,
            'provider' => $this->provider,
            'model' => $this->model,
            'created_at' => $this->created_at?->toISOString(),
            'maintenance_record_id' => $this->invoice?->maintenance_record_id,
            'retry_available' => in_array($this->status, ['uploaded', 'failed'], true) || (in_array(
                $this->status,
                ['queued', 'processing'],
                true
            ) && $this->started_at?->lte(
                now()->subMinutes(3)
            )),
        ];
    }
}
