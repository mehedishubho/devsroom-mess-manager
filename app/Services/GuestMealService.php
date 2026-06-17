<?php

namespace App\Services;

use App\Models\GuestMeal;
use App\Models\Mess;
use App\Support\MealType;
use Illuminate\Http\Request;

class GuestMealService
{
    public function list(Request $request)
    {
        $query = GuestMeal::query()->with('member')->latest('date');

        if ($memberId = $request->query('member_id')) {
            $query->where('member_id', $memberId);
        }

        return $query->paginate(50)->withQueryString();
    }

    public function create(array $data): GuestMeal
    {
        $mealValue = MealType::value($data['meal_type']);
        $chargeAmount = (float) $data['quantity'] * $mealValue;

        return GuestMeal::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $data['member_id'],
            'guest_name' => $data['guest_name'],
            'date' => $data['date'],
            'meal_type' => $data['meal_type'],
            'quantity' => $data['quantity'],
            'meal_value' => $mealValue,
            'charge_amount' => $chargeAmount,
            'entered_by' => auth()->id(),
        ]);
    }

    public function update(GuestMeal $guestMeal, array $data): GuestMeal
    {
        $mealValue = MealType::value($data['meal_type']);
        $chargeAmount = (float) $data['quantity'] * $mealValue;

        $guestMeal->update([
            'member_id' => $data['member_id'],
            'guest_name' => $data['guest_name'],
            'date' => $data['date'],
            'meal_type' => $data['meal_type'],
            'quantity' => $data['quantity'],
            'meal_value' => $mealValue,
            'charge_amount' => $chargeAmount,
        ]);

        return $guestMeal;
    }
}
