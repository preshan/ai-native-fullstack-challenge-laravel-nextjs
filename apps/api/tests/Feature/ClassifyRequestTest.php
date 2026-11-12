<?php

namespace Tests\Feature;

use App\Contracts\Classifier;
use App\Enums\Category;
use App\Models\CustomerRequest;
use App\ValueObjects\Classification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClassifyRequestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('messages')]
    public function test_classifies_keywords(string $message, string $category, float $confidence): void
    {
        $this->postJson('/requests/classify', ['message' => $message])
            ->assertOk()
            ->assertExactJson([
                'category' => $category,
                'confidence' => $confidence,
                'requestId' => null,
            ]);
    }

    public static function messages(): array
    {
        return [
            'billing wins when another keyword is also present' => ['Please help with my invoice', 'billing', 0.86],
            'sales' => ['Can we see the pricing tiers', 'sales', 0.8],
            'support' => ['The dashboard is broken today', 'support', 0.78],
            'unknown' => ['Hello there friend', 'unknown', 0.4],
            'two words are softened but stay billing' => ['Need refund', 'billing', 0.71],
        ];
    }

    public function test_short_message_can_fall_through_to_unknown(): void
    {
        $this->app->instance(Classifier::class, new FixedClassifier(Category::Billing, 0.7));

        $this->postJson('/requests/classify', ['message' => 'Need refund'])
            ->assertOk()
            ->assertJsonPath('category', 'unknown');

        $this->postJson('/requests/classify', ['message' => 'Need a refund'])
            ->assertOk()
            ->assertJsonPath('category', 'billing')
            ->assertJsonPath('confidence', 0.7);
    }

    public function test_weak_confidence_becomes_unknown(): void
    {
        $this->app->instance(Classifier::class, new FixedClassifier(Category::Sales, 0.5));

        $this->postJson('/requests/classify', ['message' => 'Hello there friend'])
            ->assertOk()
            ->assertExactJson([
                'category' => 'unknown',
                'confidence' => 0.5,
                'requestId' => null,
            ]);
    }

    public function test_bound_fake_is_used_instead_of_the_keyword_classifier(): void
    {
        $this->app->instance(Classifier::class, new FixedClassifier(Category::Sales, 0.9));

        $this->postJson('/requests/classify', ['message' => 'Hello there friend'])
            ->assertOk()
            ->assertJsonPath('category', 'sales')
            ->assertJsonPath('confidence', 0.9);
    }

    #[DataProvider('invalidMessages')]
    public function test_rejects_a_bad_message(array $body): void
    {
        $this->postJson('/requests/classify', $body)
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['message']]);
    }

    public static function invalidMessages(): array
    {
        return [
            'missing' => [[]],
            'blank' => [['message' => '   ']],
            'not a string' => [['message' => 1200]],
            'too long' => [['message' => str_repeat('a', 2001)]],
        ];
    }

    public function test_accepts_a_message_of_2000_characters(): void
    {
        $this->postJson('/requests/classify', ['message' => str_repeat('a', 2000)])
            ->assertOk()
            ->assertJsonPath('category', 'unknown');
    }

    public function test_stores_the_result_and_promotes_an_open_request(): void
    {
        $request = CustomerRequest::create(['message' => 'Refund did not arrive', 'status' => 'open']);

        $this->postJson('/requests/classify', [
            'message' => $request->message,
            'requestId' => $request->id,
        ])->assertOk()->assertJsonPath('requestId', $request->id);

        $request->refresh();
        $this->assertSame('billing', $request->category);
        $this->assertSame(0.86, $request->confidence);
        $this->assertSame('in_progress', $request->status);
    }

    public function test_does_not_change_a_resolved_request_status(): void
    {
        $request = CustomerRequest::create(['message' => 'Refund did not arrive', 'status' => 'resolved']);

        $this->postJson('/requests/classify', [
            'message' => $request->message,
            'requestId' => $request->id,
        ])->assertOk();

        $this->assertSame('resolved', $request->fresh()->status);
    }

    public function test_does_not_write_when_no_request_id_is_sent(): void
    {
        $request = CustomerRequest::create(['message' => 'Refund did not arrive', 'status' => 'open']);

        $this->postJson('/requests/classify', ['message' => $request->message])->assertOk();

        $fresh = $request->fresh();
        $this->assertNull($fresh->category);
        $this->assertSame('open', $fresh->status);
    }

    public function test_unknown_request_id_is_not_found(): void
    {
        $this->postJson('/requests/classify', [
            'message' => 'Refund did not arrive',
            'requestId' => '00000000-0000-0000-0000-000000000000',
        ])->assertNotFound();
    }
}

class FixedClassifier implements Classifier
{
    public function __construct(
        private readonly Category $category,
        private readonly float $confidence,
    ) {}

    public function classify(string $message): Classification
    {
        return new Classification($this->category, $this->confidence);
    }
}
