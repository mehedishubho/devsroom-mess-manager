<div class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-semibold leading-tight text-slate-900">
        {{ __('Hi, :name!', ['name' => auth()->user()->name]) }}
    </h1>
    <p class="mt-2 text-sm text-slate-600">
        {{ __('Your mess account is not set up yet. Please ask the manager to finish linking your account.') }}
    </p>
</div>
