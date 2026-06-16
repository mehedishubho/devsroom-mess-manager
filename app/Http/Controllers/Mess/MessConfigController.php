<?php

namespace App\Http\Controllers\Mess;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mess\UpdateMessRequest;
use App\Models\Mess;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MessConfigController extends Controller
{
    public function edit(): View
    {
        $mess = Mess::firstOrFail();

        return view('mess.settings.edit', compact('mess'));
    }

    public function update(UpdateMessRequest $request): RedirectResponse
    {
        $mess = Mess::firstOrFail();
        $mess->update($request->validated());

        return redirect()
            ->route('mess.settings.edit')
            ->with('success', __('Mess settings updated.'));
    }
}
