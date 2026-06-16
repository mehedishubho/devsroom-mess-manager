<?php

namespace Tests\Feature\Foundation;

use App\Models\Mess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Tests\TestCase;

class MessAuditableTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_mess_writes_audit_entry(): void
    {
        $mess = Mess::factory()->create(['name' => 'Original Name']);

        $mess->update(['name' => 'New Name']);

        // 1 entry for create + 1 for update
        $this->assertDatabaseCount('audits', 2);

        $audit = Audit::where('event', 'updated')->first();
        $this->assertSame('updated', $audit->event);
        $this->assertSame('Original Name', $audit->old_values['name']);
        $this->assertSame('New Name', $audit->new_values['name']);
        $this->assertSame(Mess::class, $audit->auditable_type);
        $this->assertSame($mess->id, (int) $audit->auditable_id);
    }

    public function test_creating_mess_writes_audit_entry(): void
    {
        Mess::factory()->create(['name' => 'Fresh Mess']);

        $this->assertDatabaseCount('audits', 1);
        $this->assertSame('created', Audit::first()->event);
    }
}
