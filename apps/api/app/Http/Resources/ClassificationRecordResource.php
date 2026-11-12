<?php

namespace App\Http\Resources;

use App\Models\ClassificationRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ClassificationRecord
 */
class ClassificationRecordResource extends JsonResource
{
    /**
     * @return array{id: string, requestId: string|null, requestMessage: string|null, category: string, confidence: float, source: string, createdAt: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requestId' => $this->customer_request_id,
            'requestMessage' => $this->request?->message,
            'category' => $this->category,
            'confidence' => $this->confidence,
            'source' => $this->source,
            'createdAt' => $this->created_at->toISOString(),
        ];
    }
}
