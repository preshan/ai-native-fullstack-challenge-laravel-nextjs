<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RequestListTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_first_page_with_default_size_and_total(): void
    {
        $this->seedRequests(26);

        $this->getJson('/requests')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('meta.total', 26)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_last_page_holds_the_remainder(): void
    {
        $ids = $this->seedRequests(26);

        $this->getJson('/requests?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[0]);
    }

    public function test_pages_follow_newest_first_without_gaps_or_duplicates(): void
    {
        $ids = $this->seedRequests(7);

        $seen = [];
        foreach ([1, 2, 3] as $page) {
            $seen = [...$seen, ...array_column($this->getJson("/requests?per_page=3&page={$page}")->json('data'), 'id')];
        }

        $this->assertSame(array_reverse($ids), $seen);
    }

    public function test_row_shape_note_count_and_latest_preview(): void
    {
        [$withNotes, $withoutNotes] = $this->seedRequests(2, notesFor: [3, 0]);

        $rows = collect($this->getJson('/requests')->assertOk()->json('data'))->keyBy('id');

        $this->assertSame(
            ['id', 'message', 'status', 'category', 'confidence', 'noteCount', 'latestNotePreview', 'createdAt', 'updatedAt'],
            array_keys($rows[$withNotes]),
        );
        $this->assertSame(3, $rows[$withNotes]['noteCount']);
        $this->assertSame('note 3', $rows[$withNotes]['latestNotePreview']);
        $this->assertSame(0, $rows[$withoutNotes]['noteCount']);
        $this->assertNull($rows[$withoutNotes]['latestNotePreview']);
    }

    #[DataProvider('requestCounts')]
    public function test_query_count_does_not_grow_with_rows(int $count): void
    {
        $this->seedRequests($count);

        DB::enableQueryLog();
        $this->getJson('/requests')->assertOk();

        $this->assertCount(2, DB::getQueryLog());
    }

    public static function requestCounts(): array
    {
        return ['few rows' => [3], 'more than a page' => [30]];
    }

    #[DataProvider('perPageValues')]
    public function test_per_page_is_bounded(int $perPage, int $status): void
    {
        $this->getJson("/requests?per_page={$perPage}")->assertStatus($status);
    }

    public static function perPageValues(): array
    {
        return [
            'zero' => [0, 422],
            'one' => [1, 200],
            'at max' => [100, 200],
            'over max' => [101, 422],
        ];
    }

    /**
     * Inserts requests oldest first, each with its own created_at, and returns their ids.
     *
     * @param  array<int, int>  $notesFor  note count per request, defaults to 1 each
     * @return array<int, string>
     */
    private function seedRequests(int $count, array $notesFor = []): array
    {
        $now = Carbon::parse('2026-01-01 12:00:00');
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $createdAt = $now->copy()->addMinutes($i);
            $id = (string) Str::uuid();
            $ids[] = $id;

            DB::table('customer_requests')->insert([
                'id' => $id,
                'message' => "request {$i}",
                'status' => 'open',
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            for ($n = 1; $n <= ($notesFor[$i] ?? 1); $n++) {
                DB::table('request_notes')->insert([
                    'id' => (string) Str::uuid(),
                    'request_id' => $id,
                    'body' => "note {$n}",
                    'author_name' => 'Alex',
                    'created_at' => $createdAt->copy()->addSeconds($n),
                ]);
            }
        }

        return $ids;
    }
}
