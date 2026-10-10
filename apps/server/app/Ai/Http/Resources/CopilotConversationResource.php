<?php

namespace App\Ai\Http\Resources;

use App\Models\CopilotConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CopilotConversation */
class CopilotConversationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'updated_at' => $this->updated_at->toISOString()];
    }
}
