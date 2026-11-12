<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored classification event. Append only, so re-classifying a request
 * adds a row instead of overwriting the previous result.
 *
 * @property string $id
 * @property string|null $customer_request_id
 * @property string $category
 * @property float $confidence
 * @property string $source
 */
class ClassificationRecord extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'classifications';

    protected $fillable = ['customer_request_id', 'category', 'confidence', 'source'];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(CustomerRequest::class, 'customer_request_id');
    }
}
