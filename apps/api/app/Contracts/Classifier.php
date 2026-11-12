<?php

namespace App\Contracts;

use App\ValueObjects\Classification;

interface Classifier
{
    public function classify(string $message): Classification;

    /**
     * Short identifier for this provider, stored with each classification
     * so a later model and the keyword classifier can be told apart.
     */
    public function source(): string;
}
