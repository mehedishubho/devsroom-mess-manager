<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_2fa_is_enabled_for_admin_and_super_admin(): void
    {
        $twoFactor = config('tyro-login.two_factor');

        $this->assertTrue($twoFactor['enabled']);
        $this->assertSame('admin,super-admin', $twoFactor['forced_roles']);
        $this->assertFalse($twoFactor['allow_skip']);
    }

    public function test_lockout_is_configured(): void
    {
        $lockout = config('tyro-login.lockout');

        $this->assertTrue($lockout['enabled']);
        $this->assertSame(5, $lockout['max_attempts']);
        $this->assertSame(15, $lockout['duration_minutes']);
    }
}
