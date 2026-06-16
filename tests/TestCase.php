<?php

namespace Tests;

use HasinHayder\Tyro\Models\Role;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['mess.active_mess_id' => 1]);
    }

    protected function seedTyroRoles(): void
    {
        Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
        Role::firstOrCreate(['slug' => 'super-admin'], ['name' => 'Super Admin']);
        Role::firstOrCreate(['slug' => 'user'], ['name' => 'User']);
    }
}
