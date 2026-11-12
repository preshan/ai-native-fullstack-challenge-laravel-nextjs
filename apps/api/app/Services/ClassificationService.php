<?php

namespace App\Services;

use App\Contracts\Classifier;
use App\Enums\Category;
use App\ValueObjects\Classification;

class ClassificationService
{
    public function __construct(
        private readonly Classifier $classifier,
        private readonly RequestsService $requests,
    ) {}

    public function classify(string $message, ?string $requestId): Classification
    {
        $classification = $this->applyRules($message, $this->classifier->classify($message));

        if ($requestId !== null) {
            $existing = $this->requests->getById($requestId);
            $existing->category = $classification->category->value;
            $existing->confidence = $classification->confidence;
            if ($existing->status === 'open') {
                $existing->status = 'in_progress';
            }
            $this->requests->save($existing);
        }

        return $classification;
    }

    private function applyRules(string $message, Classification $classification): Classification
    {
        $category = $classification->category;
        $confidence = $classification->confidence;

        if ($this->wordCount($message) < 3 && $category !== Category::Unknown) {
            $confidence = max(0.5, $confidence - 0.15);
        }

        if ($confidence < 0.55) {
            $category = Category::Unknown;
        }

        return new Classification($category, $confidence);
    }

    private function wordCount(string $message): int
    {
        return count(preg_split('/\s+/', $message));
    }
}
