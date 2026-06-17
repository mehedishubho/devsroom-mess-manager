<?php

namespace Tests\Feature\Mess;

use App\Models\Mess;
use App\Models\Payment;
use App\Services\BillPreviewService;
use App\Support\PaymentMethod;
use App\Support\PaymentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BillPreviewCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Cache::flush();
    }

    public function test_cache_hits_are_returned_for_same_month(): void
    {
        $service = app(BillPreviewService::class);
        $year = now()->year;
        $month = now()->month;

        $first = $service->preview($year, $month);
        $second = $service->preview($year, $month);

        $this->assertSame($first, $second);
    }

    public function test_payment_creation_invalidates_cache(): void
    {
        $service = app(BillPreviewService::class);
        $year = now()->year;
        $month = now()->month;

        $first = $service->preview($year, $month);

        Payment::factory()->create([
            'type' => PaymentType::ADVANCE_DEPOSIT,
            'method' => PaymentMethod::CASH,
            'date' => now()->toDateString(),
        ]);

        $second = $service->preview($year, $month);

        $this->assertNotSame($first, $second);
    }
}
