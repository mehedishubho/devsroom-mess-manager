---
status: diagnosed
problem: super-admin bypasses /onboarding when no mess exists
phase: 01-foundation
created: 2026-06-17T00:25:00Z
updated: 2026-06-17T00:25:00Z
---

# Debug: Super-admin bypasses /onboarding when no mess

## Symptom

User deleted the only mess via `App\Models\Mess::first()->delete()` then
logged out and back in as super-admin. The redirect went to `/dashboard`
(Tyro admin UI) instead of `/onboarding` (the create-your-mess form).

## Root cause

The `EnsureMessExists` middleware in `routes/web.php` is applied **only to
the manager (admin) group**:

```php
Route::middleware(['auth', 'role:admin', EnsureMessExists::class])->group(function () {
    Route::get('/home', ...);
    Route::get('/mess/settings', ...);
    // ...
});
```

The super-admin's `/onboarding` routes are in a different group with no
such guard:

```php
Route::middleware(['auth', 'role:super-admin'])->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');
});
```

The post-login redirect closure in `app/Providers/AppServiceProvider.php`
returns `/dashboard` unconditionally for super-admin:

```php
if ($user->hasRole('super-admin')) {
    return '/dashboard';
}
```

So a super-admin whose only mess was deleted gets a Tyro Dashboard
pointing at mess data that does not exist yet.

## Fix

1. Update the post-login closure in `AppServiceProvider::boot()` to check
   for an existing mess; if none, route super-admin to `/onboarding`
   instead of `/dashboard`.
2. Add `EnsureMessExists` middleware to the super-admin route group
   (so any super-admin navigation to `/onboarding` itself still works
   to create the mess).
3. Add a regression test that proves the behavior:
   `test_super_admin_redirected_to_onboarding_when_no_mess`.

## Affected call sites

- `app/Providers/AppServiceProvider.php` — post-login closure
- `routes/web.php` — super-admin onboarding group

## Plan

Add the closure guard + middleware + test in Plan 01.5.
