<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_2fa_is_disabled_in_development(): void
    {
        $twoFactor = config('tyro-login.two_factor');

        // 2FA is disabled in dev/test (no email system). Re-enable in prod
        // by setting TYRO_LOGIN_2FA_ENABLED=true and
        // TYRO_LOGIN_2FA_FORCED_ROLES=admin,super-admin.
        $this->assertEmpty($twoFactor['enabled']);
    }

    public function test_lockout_is_configured(): void
    {
        $lockout = config('tyro-login.lockout');

        $this->assertTrue($lockout['enabled']);
        $this->assertSame(5, $lockout['max_attempts']);
        $this->assertSame(15, $lockout['duration_minutes']);
    }
}
