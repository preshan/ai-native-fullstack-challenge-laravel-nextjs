<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestDatabaseTest extends TestCase
{
    public function test_suite_uses_the_test_database(): void
    {
        $this->assertSame('request_desk_test', DB::connection()->getDatabaseName());
    }
}
