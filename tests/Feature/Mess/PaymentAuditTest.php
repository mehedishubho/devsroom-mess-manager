<?php

namespace Tests\Feature\Mess;

use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    public function test_creating_payment_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::where('slug', 'admin')->first());
        $this->actingAs($user);

        $payment = Payment::factory()->create();

        $this->assertDatabaseHas('audits', [
            'auditable_type' => Payment::class,
            'auditable_id' => $payment->id,
            'event' => 'created',
        ]);
    }
}
