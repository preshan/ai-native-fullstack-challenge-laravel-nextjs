<?php

namespace App\Http\Resources;

use App\ValueObjects\Classification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Classification
 */
class ClassificationResource extends JsonResource
{
    public static $wrap = null;

    public function __construct(Classification $classification, private readonly ?string $requestId)
    {
        parent::__construct($classification);
    }

    /**
     * @return array{category: string, confidence: float, requestId: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'category' => $this->category->value,
            'confidence' => $this->confidence,
            'requestId' => $this->requestId,
        ];
    }
}
