<?php

namespace App\Services;

use App\Contracts\Classifier;
use App\Enums\Category;
use App\Models\ClassificationRecord;
use App\ValueObjects\Classification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ClassificationService
{
    public function __construct(
        private readonly Classifier $classifier,
        private readonly RequestsService $requests,
    ) {}

    public function classify(string $message, ?string $requestId): Classification
    {
        $classification = $this->applyRules($message, $this->classifier->classify($message));

        DB::transaction(function () use ($classification, $requestId) {
            if ($requestId !== null) {
                $existing = $this->requests->getById($requestId);
                $existing->category = $classification->category->value;
                $existing->confidence = $classification->confidence;
                if ($existing->status === 'open') {
                    $existing->status = 'in_progress';
                }
                $this->requests->save($existing);
            }

            $this->record($classification, $requestId);
        });

        return $classification;
    }

    /**
     * Newest first. Optional category filter, same pagination limits as the list.
     *
     * @return LengthAwarePaginator<int, ClassificationRecord>
     */
    public function history(?Category $category, int $perPage): LengthAwarePaginator
    {
        return ClassificationRecord::query()
            ->with('request:id,message')
            ->when($category, fn ($query) => $query->where('category', $category->value))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function record(Classification $classification, ?string $requestId): void
    {
        ClassificationRecord::create([
            'customer_request_id' => $requestId,
            'category' => $classification->category->value,
            'confidence' => $classification->confidence,
            'source' => $this->classifier->source(),
        ]);
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
