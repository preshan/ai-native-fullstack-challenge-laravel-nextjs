<?php

namespace Tests\Feature;

use App\Models\CustomerRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The web table refetches GET /requests after each action, so the list must
 * return what the action stored.
 */
class RequestFreshnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_returns_status_after_status_change(): void
    {
        $request = CustomerRequest::create(['message' => 'Dashboard is broken', 'status' => 'open']);

        $this->patchJson("/requests/{$request->id}/status", ['status' => 'resolved'])->assertOk();

        $this->getJson('/requests')
            ->assertOk()
            ->assertJsonPath('data.0.id', $request->id)
            ->assertJsonPath('data.0.status', 'resolved');
    }

    #[DataProvider('statusesBeforeClassify')]
    public function test_list_returns_category_and_status_after_classify(string $before, string $after): void
    {
        $request = CustomerRequest::create(['message' => 'Refund did not arrive', 'status' => $before]);

        $this->postJson('/requests/classify', ['message' => $request->message, 'requestId' => $request->id])
            ->assertOk();

        $this->getJson('/requests')
            ->assertOk()
            ->assertJsonPath('data.0.category', 'billing')
            ->assertJsonPath('data.0.confidence', 0.86)
            ->assertJsonPath('data.0.status', $after);
    }

    public static function statusesBeforeClassify(): array
    {
        return [
            'open is promoted' => ['open', 'in_progress'],
            'resolved is kept' => ['resolved', 'resolved'],
        ];
    }
}
