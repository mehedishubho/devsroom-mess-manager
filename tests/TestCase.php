<?php

namespace Tests;

use App\Models\Mess;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mess.active_mess_id' => 1]);
        Mess::forgetActiveIdCache();
    }

    protected function seedTyroRoles(): void
    {
        Role::firstOrCreate(['slug' => 'manager'], ['name' => 'Manager']);
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'mess-member'], ['name' => 'Mess Member']);
    }
}
