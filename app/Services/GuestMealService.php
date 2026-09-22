<?php

namespace App\Services;

use App\Models\GuestMeal;
use App\Models\Mess;
use App\Support\MealType;
use Illuminate\Http\Request;

class GuestMealService
{
    public function __construct(private readonly BillPreviewService $billPreview) {}

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

        $guestMeal = GuestMeal::create([
            'mess_id' => Mess::activeId(),
            'member_id' => $data['member_id'],
            'guest_name' => $data['guest_name'],
            'date' => $data['date'],
            'meal_type' => $data['meal_type'],
            'quantity' => $data['quantity'],
            'meal_value' => $mealValue,
            // Meal UNITS (quantity × weight), not money — billed at the live
            // meal rate by BillPreviewService (GUEST-02).
            'charge_amount' => $chargeAmount,
            'entered_by' => auth()->id(),
        ]);

        $this->invalidateForDate($data['date']);

        return $guestMeal;
    }

    public function update(GuestMeal $guestMeal, array $data): GuestMeal
    {
        $mealValue = MealType::value($data['meal_type']);
        $chargeAmount = (float) $data['quantity'] * $mealValue;

        $originalDate = $guestMeal->date?->toDateString();

        $guestMeal->update([
            'member_id' => $data['member_id'],
            'guest_name' => $data['guest_name'],
            'date' => $data['date'],
            'meal_type' => $data['meal_type'],
            'quantity' => $data['quantity'],
            'meal_value' => $mealValue,
            'charge_amount' => $chargeAmount,
        ]);

        $this->invalidateForDate($originalDate);
        $this->invalidateForDate($guestMeal->date->toDateString());

        return $guestMeal;
    }

    /**
     * Remove a guest meal (GUEST-04). Route-level month.open middleware
     * already refuses deletes for closed months.
     */
    public function delete(GuestMeal $guestMeal): void
    {
        $date = $guestMeal->date?->toDateString();

        $guestMeal->delete();

        $this->invalidateForDate($date);
    }

    /**
     * Drop the cached bill preview for the (year, month) of a Y-m-d date so
     * guest-meal changes show up on the next bill preview read instead of
     * waiting out the 1-hour cache TTL.
     */
    private function invalidateForDate(?string $date): void
    {
        if ($date === null) {
            return;
        }

        $this->billPreview->invalidate((int) substr($date, 0, 4), (int) substr($date, 5, 2));
    }
}
