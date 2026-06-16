<?php

namespace Tests\Feature\Foundation;

use App\Models\Mess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mess_table_is_queryable_without_scope(): void
    {
        Mess::factory()->create(['id' => 1, 'name' => 'Active Mess']);
        Mess::factory()->create(['id' => 2, 'name' => 'Other Mess']);

        $this->assertSame(2, Mess::count());
    }
}
