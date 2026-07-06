@extends('tyro-dashboard::layouts.admin')

@section('title', 'Create User')

@section('breadcrumb')
<a href="{{ route($dashboardRoute::name('index')) }}">Dashboard</a>
<span class="breadcrumb-separator">/</span>
<a href="{{ route($dashboardRoute::name('users.index')) }}">Users</a>
<span class="breadcrumb-separator">/</span>
<span>Create</span>
@endsection

@section('content')
<div class="page-header">
    <div class="page-header-row">
        <div>
            <h1 class="page-title">Create User</h1>
            <p class="page-description">Add a new user to the system.</p>
        </div>
        <a href="{{ route($dashboardRoute::name('users.index')) }}" class="btn btn-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back to Users
        </a>
    </div>
</div>

<div class="card">
    <form action="{{ route($dashboardRoute::name('users.store')) }}" method="POST">
        @csrf
        <div class="card-body">
            <div class="form-row">
                <div class="form-group">
                    <label for="name" class="form-label">Name</label>
                    <input type="text" id="name" name="name" class="form-input @error('name') is-invalid @enderror" value="{{ old('name') }}" required placeholder="John Doe">
                    @error('name')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" class="form-input @error('email') is-invalid @enderror" value="{{ old('email') }}" required placeholder="john@example.com">
                    @error('email')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="position-relative" style="position: relative;">
                        <input type="password" id="password" name="password" class="form-input @error('password') is-invalid @enderror" required placeholder="••••••••" style="padding-right: 2.5rem;">
                        <button type="button" style="position: absolute; right: 0.625rem; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; color: #94a3b8; cursor: pointer; line-height: 1;" onclick="togglePassword('password', this)" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </button>
                    </div>
                    @error('password')
                        <span class="form-error">{{ $message }}</span>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <div class="position-relative" style="position: relative;">
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-input" required placeholder="••••••••" style="padding-right: 2.5rem;">
                        <button type="button" style="position: absolute; right: 0.625rem; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; color: #94a3b8; cursor: pointer; line-height: 1;" onclick="togglePassword('password_confirmation', this)" aria-label="Show password">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="margin-bottom: 0.75rem; display: block;">Assign Roles</label>
                <div class="role-grid">
                    @foreach($roles as $role)
                    <label class="role-card">
                        <div class="role-card-header">
                            <div class="role-title">{{ $role->name }}</div>
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="role-checkbox" {{ in_array($role->id, old('roles', [])) ? 'checked' : '' }}>
                        </div>
                        <div class="role-desc">{{ $role->slug }}</div>
                    </label>
                    @endforeach
                </div>
                @error('roles')
                    <span class="form-error">{{ $message }}</span>
                @enderror
            </div>
        </div>
        <div class="card-footer" style="display: flex; gap: 0.75rem;">
            <button type="submit" class="btn btn-primary">Create User</button>
            <a href="{{ route($dashboardRoute::name('users.index')) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('styles')
<style>
    .role-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.75rem;
    }
    .role-card {
        position: relative;
        display: flex;
        flex-direction: column;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 0.5rem;
        background-color: var(--card);
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
    }
    .role-card:hover {
        border-color: var(--primary);
        box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
    }
    .role-card:has(.role-checkbox:checked) {
        border-color: color-mix(in srgb, var(--primary), transparent 50%);
        background-color: color-mix(in srgb, var(--primary), transparent 96%);
        box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--primary), transparent 50%);
    }
    .role-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 0.25rem;
    }
    .role-title {
        font-weight: 600;
        font-size: 0.8125rem;
        color: var(--foreground);
    }
    .role-checkbox {
        width: 1rem;
        height: 1rem;
        border-radius: 4px;
        border: 1px solid var(--border);
        accent-color: var(--primary);
        cursor: pointer;
    }
    .role-desc {
        font-size: 0.75rem;
        color: var(--muted-foreground);
    }
</style>
@endpush

@push('scripts')
<script>
    function togglePassword(id, btn) {
        const input = document.getElementById(id);
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        btn.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        btn.innerHTML = isPassword
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>';
    }
</script>
@endpush
@endsection
