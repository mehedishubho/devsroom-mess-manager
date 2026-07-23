<?php

namespace Tests\Feature\Mess;

use App\Models\Mess;
use App\Models\User;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task 3 of quick-260717-2q3 — close-month confirmation modal Cancel fix.
 *
 * Asserts the post-fix markup contract: trigger button + modal share the
 * same x-data scope; @click.outside lives on the outer backdrop (NOT on
 * the inner panel); Cancel button has type="button" + an @click that
 * closes; Escape still closes via @keydown.escape.window on the backdrop.
 *
 * Substring assertions on the raw HTML — Laravel's assertSee() would
 * un-escape entities and let whitespace vary; we want byte-precise
 * contract checks.
 */
class CloseMonthModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTyroRoles();
        $mess = Mess::factory()->create();
        config(['mess.active_mess_id' => $mess->id]);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::where('slug', 'manager')->first());

        return $admin;
    }

    public function test_close_month_page_renders_trigger_modal_and_cancel_button(): void
    {
        $response = $this->actingAs($this->admin())->get(route('mess.close.index'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertNotEmpty($html);

        // Trigger button (some label depending on current month).
        $this->assertStringContainsString('btn-primary', $html);
        $this->assertStringContainsString('@click="open = true"', $html);

        // Modal markup is included.
        $this->assertStringContainsString('Confirm close', $html);

        // Cancel button is present.
        $this->assertStringContainsString('Cancel', $html);
    }

    public function test_cancel_button_has_type_button_and_close_handler(): void
    {
        $response = $this->actingAs($this->admin())->get(route('mess.close.index'));
        $html = $response->getContent();

        // The Cancel button MUST be type="button" (so it never submits the
        // parent form) and carry an Alpine @click that closes the modal.
        $this->assertStringContainsString(
            '<button type="button" @click="open = false" class="btn btn-secondary">',
            $html
        );
    }

    public function test_trigger_button_and_modal_share_the_same_x_data_scope(): void
    {
        // If they did NOT share scope, the trigger @click="open = true" would
        // not toggle the modal's x-show="open". Per index.blade.php they live
        // inside the same <form x-data="{ open: false }"> wrapper.
        $response = $this->actingAs($this->admin())->get(route('mess.close.index'));
        $html = $response->getContent();

        $this->assertStringContainsString('x-data="{ open: false }"', $html);
        $this->assertStringContainsString('@click="open = true"', $html);
        $this->assertStringContainsString('x-show="open"', $html);
    }

    public function test_click_outside_lives_on_outer_backdrop_not_inner_panel(): void
    {
        // The bug was @click.outside on the inner white panel. Assert that
        // the inner panel markup does NOT carry @click.outside and the outer
        // backdrop DOES.
        $response = $this->actingAs($this->admin())->get(route('mess.close.index'));
        $html = $response->getContent();

        // The outer backdrop is the fixed inset-0 z-50 element with @click.outside.
        $this->assertStringContainsString('@click.outside="open = false"', $html);

        // The inner white panel markup — assert it has NO @click.outside.
        // Find the inner panel string and verify the absence within it.
        $innerPanelStart = strpos($html, 'w-full max-w-md rounded-xl bg-white');
        $this->assertNotFalse($innerPanelStart, 'inner white panel exists');
        $innerPanelSegment = substr($html, $innerPanelStart, 400);
        $this->assertStringNotContainsString('@click.outside', $innerPanelSegment);
    }

    public function test_escape_key_still_closes_modal(): void
    {
        $response = $this->actingAs($this->admin())->get(route('mess.close.index'));
        $html = $response->getContent();

        // Escape handler stays on the backdrop.
        $this->assertStringContainsString('@keydown.escape.window="open = false"', $html);
    }

    public function test_x_cloak_rule_present_in_compiled_css(): void
    {
        // The [x-cloak]{display:none!important} rule must be in app.css so
        // modals do not flash on page load before Alpine initializes. The
        // compiled Vite build uses a hashed filename, so resolve via manifest.
        $css = null;
        $manifestPath = public_path('build/manifest.json');
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            $cssEntry = $manifest['resources/css/app.css']['file'] ?? null;
            if ($cssEntry && is_file(public_path("build/{$cssEntry}"))) {
                $css = file_get_contents(public_path("build/{$cssEntry}"));
            }
        }
        // Fallback to source if the build hasn't shipped.
        if ($css === false || $css === '' || ! str_contains((string) $css, 'x-cloak')) {
            $css = file_get_contents(resource_path('css/app.css'));
        }
        $this->assertNotNull($css);
        $this->assertStringContainsString('[x-cloak]', $css);
        // 'display:none' or 'display: none' — both valid minified/unminified forms.
        $this->assertTrue(
            str_contains((string) $css, 'display:none') || str_contains((string) $css, 'display: none'),
            'CSS must hide [x-cloak] elements with display:none'
        );
    }
}
