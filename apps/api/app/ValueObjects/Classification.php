<?php

namespace App\ValueObjects;

use App\Enums\Category;

readonly class Classification
{
    public function __construct(
        public Category $category,
        public float $confidence,
    ) {}
}
