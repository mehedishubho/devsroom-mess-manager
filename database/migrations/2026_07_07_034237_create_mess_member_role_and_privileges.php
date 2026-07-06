<?php

use HasinHayder\Tyro\Models\Privilege;
use HasinHayder\Tyro\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create the "Mess Member" role (descriptive alias for the existing user role)
        $messMember = Role::updateOrCreate(
            ['slug' => 'mess-member'],
            ['name' => 'Mess Member']
        );

        // Create privileges for members
        $privilegeData = [
            ['name' => 'View Own Profile', 'slug' => 'member.profile.view', 'description' => 'View and edit own profile'],
            ['name' => 'View Own Meals', 'slug' => 'member.meals.view', 'description' => 'View own meal entries'],
            ['name' => 'Request Meal Off', 'slug' => 'member.meal-off.request', 'description' => 'Submit meal-off requests'],
            ['name' => 'View Own Bill', 'slug' => 'member.bill.view', 'description' => 'View own bill preview and reports'],
            ['name' => 'View Own Payments', 'slug' => 'member.payments.view', 'description' => 'View own payment history'],
            ['name' => 'View Reports', 'slug' => 'member.reports.view', 'description' => 'View own monthly reports and statements'],
        ];

        $privilegeIds = [];
        foreach ($privilegeData as $data) {
            $privilege = Privilege::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
            $privilegeIds[] = $privilege->id;
        }

        // Assign all member privileges to mess-member and user roles
        $messMember->privileges()->syncWithoutDetaching($privilegeIds);
        $user = Role::where('slug', 'user')->first();
        if ($user) {
            $user->privileges()->syncWithoutDetaching($privilegeIds);
        }
    }

    public function down(): void
    {
        $role = Role::where('slug', 'mess-member')->first();
        if ($role) {
            $role->privileges()->detach();
            $role->delete();
        }

        // Also remove the member privileges from the user role
        $slugs = ['member.profile.view', 'member.meals.view', 'member.meal-off.request',
                  'member.bill.view', 'member.payments.view', 'member.reports.view'];
        foreach ($slugs as $slug) {
            $privilege = Privilege::where('slug', $slug)->first();
            if ($privilege) {
                $privilege->delete();
            }
        }
    }
};
