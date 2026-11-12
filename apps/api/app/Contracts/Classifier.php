<?php

namespace App\Contracts;

use App\ValueObjects\Classification;

interface Classifier
{
    public function classify(string $message): Classification;
}
