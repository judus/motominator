<?php

namespace App\Ai\Http\Resources;

use App\Models\CopilotMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CopilotMessage */
class CopilotMessageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
