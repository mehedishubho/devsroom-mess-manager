<?php

declare(strict_types=1);

namespace Tests\Feature\Report;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Member;
use App\Models\Mess;
use App\Models\Payment;
use App\Models\User;
use App\Support\ExpenseKind;
use App\Support\MemberStatus;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T-05-01-04 mitigation regression test (Plan 05-01 Task 2).
 *
 * Debugbar must NEVER inject its HTML payload into a PDF response body —
 * Dompdf renders the response body as a PDF, and any appended <script>
 * or debugbar markup corrupts the PDF. The exclude_paths / except rule
 * (config('debugbar.except') includes '*.pdf') is the structural mitigation;
 * this test ENFORCES it end-to-end with Debugbar explicitly enabled.
 */
class PdfDebugbarExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
        Mess::forgetActiveIdCache();

        // Explicitly enable Debugbar for this test. This proves the exclusion
        // works regardless of the .env default (which ships as false).
        config(['debugbar.enabled' => true]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'admin')->first());

        return $admin;
    }

    private function seedMonthlyData(): void
    {
        $messId = Mess::activeId();
        $date = now()->toDateString();

        $member = Member::factory()->create([
            'mess_id' => $messId,
            'status' => MemberStatus::ACTIVE,
            'name' => 'PDF Debugbar Test Member',
        ]);

        $bazar = ExpenseCategory::factory()->create([
            'mess_id' => $messId,
            'kind' => ExpenseKind::BAZAR,
        ]);
        Expense::factory()->create([
            'mess_id' => $messId,
            'expense_category_id' => $bazar->id,
            'date' => $date,
            'amount' => '500.00',
        ]);

        Payment::factory()->create([
            'mess_id' => $messId,
            'member_id' => $member->id,
            'date' => $date,
            'amount' => '1000.00',
        ]);
    }

    public function test_monthly_pdf_body_contains_no_debugbar_payload_when_debugbar_enabled(): void
    {
        $this->seedMonthlyData();

        // Sanity: confirm Debugbar is enabled for this request
        $this->assertTrue(config('debugbar.enabled'));
        // Sanity: confirm *.pdf is in the exclusion list
        $this->assertContains('*.pdf', config('debugbar.except', []));

        $response = $this->actingAs($this->admin())
            ->get(route('mess.reports.monthly.pdf'));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $body = $response->getContent();
        $this->assertNotEmpty($body, 'PDF body must not be empty');
        $this->assertSame(
            '%PDF',
            substr($body, 0, 4),
            'Response must start with %PDF magic — got: '.substr($body, 0, 16)
        );

        // The core T-05-01-04 assertion: no debugbar markup in the body.
        $this->assertStringNotContainsStringIgnoringCase('debugbar', $body);
        $this->assertStringNotContainsStringIgnoringCase('phpdebugbar', $body);
        $this->assertStringNotContainsString('<script>phpdebugbar', $body);
    }
}
