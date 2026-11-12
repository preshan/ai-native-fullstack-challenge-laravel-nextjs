<?php

namespace App\Http\Resources;

use App\Models\CustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerRequest
 */
class RequestListItemResource extends JsonResource
{
    /**
     * @return array{id: string, message: string, status: string, category: string|null, confidence: float|null, noteCount: int, latestNotePreview: string|null, createdAt: string, updatedAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            'status' => $this->status,
            'category' => $this->category,
            'confidence' => $this->confidence,
            'noteCount' => (int) $this->notes_count,
            'latestNotePreview' => $this->latest_note_preview,
            'createdAt' => $this->created_at->toISOString(),
            'updatedAt' => $this->updated_at->toISOString(),
        ];
    }
}
