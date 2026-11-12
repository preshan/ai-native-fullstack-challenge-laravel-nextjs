<?php

namespace Tests\Feature;

use App\Models\ClassificationRecord;
use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_classify_records_a_history_row(): void
    {
        $request = CustomerRequest::create(['message' => 'Please help with my invoice', 'status' => 'open']);

        $this->postJson('/requests/classify', [
            'message' => $request->message,
            'requestId' => $request->id,
        ])->assertOk();

        $this->assertDatabaseCount('classifications', 1);

        $row = ClassificationRecord::query()->first();
        $this->assertSame($request->id, $row->customer_request_id);
        $this->assertSame('billing', $row->category);
        $this->assertSame(0.86, $row->confidence);
        $this->assertSame('keyword', $row->source);
    }

    public function test_classify_without_a_request_id_still_records(): void
    {
        $this->postJson('/requests/classify', ['message' => 'Please help with my invoice'])->assertOk();

        $this->assertDatabaseCount('classifications', 1);
        $this->assertNull(ClassificationRecord::query()->first()->customer_request_id);
    }

    public function test_history_lists_newest_first_with_pagination_shape(): void
    {
        $this->postJson('/requests/classify', ['message' => 'Please help with my invoice'])->assertOk();
        $this->postJson('/requests/classify', ['message' => 'Can we see the pricing tiers'])->assertOk();

        $response = $this->getJson('/requests/history')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'requestId', 'requestMessage', 'category', 'confidence', 'source', 'createdAt']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $categories = array_column($response->json('data'), 'category');
        $this->assertSame(['sales', 'billing'], $categories);
    }

    public function test_history_filters_by_category(): void
    {
        $this->postJson('/requests/classify', ['message' => 'Please help with my invoice'])->assertOk();
        $this->postJson('/requests/classify', ['message' => 'Can we see the pricing tiers'])->assertOk();

        $this->getJson('/requests/history?category=billing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category', 'billing');
    }

    public function test_history_rejects_an_unknown_category(): void
    {
        $this->getJson('/requests/history?category=nonsense')
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['category']]);
    }
}
