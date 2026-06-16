# Conventions

Code style, patterns, and standards observed in the codebase.

## Code Style

- **Laravel Pint** is configured (Laravel preset). Run `vendor/bin/pint` before commits.
- **PSR-12** is the base standard.
- **EditorConfig** present (`.editorconfig`) — uses 4-space indent, LF line endings, UTF-8.

## PHP

### Attribute-Based Model Configuration

The `User` model uses PHP 8 attributes for fillable/hidden fields (Laravel 11+ style):

```php
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
```

- New models should follow the same pattern (use `#[Fillable]`, `#[Hidden]` attributes)
- Avoid the older `$fillable` / `$hidden` property arrays when starting fresh

### Casts Method (Laravel 11+ style)

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

- Use the `casts()` method, not the deprecated `$casts` property

### Imports

- One `use` per import, alphabetically grouped
- Example from `User.php`:
  ```php
  use Database\Factories\UserFactory;
  use Illuminate\Database\Eloquent\Attributes\Fillable;
  use Illuminate\Foundation\Auth\User as Authenticatable;
  use Laravel\Sanctum\HasApiTokens;
  use HasinHayder\Tyro\Concerns\HasTyroRoles;
  use HasinHayder\TyroLogin\Traits\HasTwoFactorAuth;
  ```

### Migration Style

Anonymous-class migrations (Laravel default):

```php
return new class extends Migration
{
    public function up(): void { ... }
    public function down(): void { ... }
};
```

- Use anonymous classes, not named classes
- Always implement both `up()` and `down()`
- Use `Blueprint` typed parameter
- Foreign keys use `foreignId('user_id')` + `constrained()` pattern

### Test Style

- Extends `Tests\TestCase`
- Test methods prefixed with `test_` (PHPUnit snake_case, not Pest)
- `void` return type on test methods

## Database

- **snake_case** for all column and table names
- **Plural** table names (`users`, `personal_access_tokens`)
- **Timestamps** (`$table->timestamps()`) on all domain tables
- **Soft deletes** preferred for user-facing entities (not seen in skeleton, but standard)
- **Foreign keys**: `foreignId('user_id')->constrained()->cascadeOnDelete()` pattern

## Authorization

- Use Tyro roles/privileges, not Laravel Gates/Policies (initially)
- Check role: `$user->hasRole('admin')` (Tyro API)
- Use middleware `role:admin` for route protection

## Frontend

- **Tailwind CSS v4** with `@tailwindcss/vite` plugin
- **No JavaScript framework** — vanilla JS in `resources/js/app.js`
- **Axios** for AJAX (configured but no usage yet)
- All Blade, no Inertia/Livewire

## Error Handling

- Skeleton has no custom error handling
- Follow Laravel defaults: throw `ValidationException`, `ModelNotFoundException`, `AuthorizationException` from actions
- Use Form Requests for validation (`app/Http/Requests/`)
- Use Policies for authorization

## Service Registration

- Register bindings in `AppServiceProvider::register()` for app-wide singletons
- Use `boot()` for view composers, gates, observer registration

## Git

- Initialized in this session
- No commits yet
- `.gitignore` covers vendor, node_modules, .env, storage logs/cache, IDE folders, build artifacts

## PHPStan / Static Analysis

- **Not configured** — consider adding `larastan` (`nunomaduro/larastan`) for type safety

## Things To Avoid

- Don't add `$fillable` / `$hidden` property arrays to new models (use attributes)
- Don't use the deprecated `$casts` property (use the `casts()` method)
- Don't create named migration classes (use anonymous)
- Don't bypass Tyro for auth/roles (don't roll custom auth when Tyro provides it)
- Don't use hyphens in database names (per taste preference: `devsroom_mess_management`)
- Don't assume default DB credentials (per taste preference — verify with user)
