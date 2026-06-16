<?php

namespace App\Http\Controllers;

use App\Http\Requests\Onboarding\CreateMessRequest;
use App\Models\Mess;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Mess::withoutGlobalScopes()->exists()) {
            return redirect()->route('home');
        }

        return view('onboarding.create');
    }

    public function store(CreateMessRequest $request): RedirectResponse
    {
        if (Mess::withoutGlobalScopes()->exists()) {
            return redirect()->route('home');
        }

        $data = $request->validated();

        $mess = Mess::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'monthly_rent' => $data['monthly_rent'],
            'manager_contact' => $data['manager_contact'] ?? null,
            'status' => 'active',
        ]);

        $settings = [
            ['key' => 'meal_breakfast', 'value' => ['amount' => (float) $data['meal_breakfast']], 'type' => 'number', 'group' => 'meals', 'description' => 'Breakfast meal value'],
            ['key' => 'meal_lunch', 'value' => ['amount' => (float) $data['meal_lunch']], 'type' => 'number', 'group' => 'meals', 'description' => 'Lunch meal value'],
            ['key' => 'meal_dinner', 'value' => ['amount' => (float) $data['meal_dinner']], 'type' => 'number', 'group' => 'meals', 'description' => 'Dinner meal value'],
            ['key' => 'currency', 'value' => ['code' => $data['currency']], 'type' => 'string', 'group' => 'general', 'description' => 'Currency code'],
            ['key' => 'date_format', 'value' => ['format' => $data['date_format']], 'type' => 'string', 'group' => 'general', 'description' => 'Date format'],
            ['key' => 'auto_monthly_close', 'value' => ['enabled' => false], 'type' => 'boolean', 'group' => 'general', 'description' => 'Auto-close month (reserved for v2)'],
        ];

        foreach ($settings as $row) {
            Setting::create(array_merge($row, ['mess_id' => $mess->id]));
        }

        return redirect()
            ->route('home')
            ->with('success', __('Your mess has been created. Welcome aboard!'));
    }
}
