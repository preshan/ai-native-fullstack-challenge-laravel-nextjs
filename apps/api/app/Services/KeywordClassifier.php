<?php

namespace App\Services;

use App\Contracts\Classifier;
use App\Enums\Category;
use App\ValueObjects\Classification;

/**
 * Deterministic keyword classifier. Bound to Classifier so it can be swapped.
 */
class KeywordClassifier implements Classifier
{
    public function classify(string $message): Classification
    {
        $text = mb_strtolower($message);

        if (preg_match('/bill|invoice|payment|refund|charge/', $text)) {
            return new Classification(Category::Billing, 0.86);
        }
        if (preg_match('/buy|pricing|demo|sales|upgrade|plan/', $text)) {
            return new Classification(Category::Sales, 0.8);
        }
        if (preg_match('/help|broken|error|issue|support|bug/', $text)) {
            return new Classification(Category::Support, 0.78);
        }

        return new Classification(Category::Unknown, 0.4);
    }

    public function source(): string
    {
        return 'keyword';
    }
}
