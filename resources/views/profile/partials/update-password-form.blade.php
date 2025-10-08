<section class="mb-4">
    <header>
        <h2 class="fs-4 fw-medium text-dark">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-2 text-muted small">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-4">
        @csrf
        @method('put')

        <!-- Current Password -->
        <div class="mb-3">
            <label for="update_password_current_password" class="form-label">{{ __('Current Password') }}</label>
            <input
                id="update_password_current_password"
                name="current_password"
                type="password"
                class="form-control"
                autocomplete="current-password"
            />
            @if ($errors->updatePassword->has('current_password'))
                <div class="invalid-feedback">
                    {{ $errors->updatePassword->first('current_password') }}
                </div>
            @endif
        </div>

        <!-- New Password -->
        <div class="mb-3">
            <label for="update_password_password" class="form-label">{{ __('New Password') }}</label>
            <input
                id="update_password_password"
                name="password"
                type="password"
                class="form-control"
                autocomplete="new-password"
            />
            @if ($errors->updatePassword->has('password'))
                <div class="invalid-feedback">
                    {{ $errors->updatePassword->first('password') }}
                </div>
            @endif
        </div>

        <!-- Confirm Password -->
        <div class="mb-3">
            <label for="update_password_password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
            <input
                id="update_password_password_confirmation"
                name="password_confirmation"
                type="password"
                class="form-control"
                autocomplete="new-password"
            />
            @if ($errors->updatePassword->has('password_confirmation'))
                <div class="invalid-feedback">
                    {{ $errors->updatePassword->first('password_confirmation') }}
                </div>
            @endif
        </div>

        <!-- Save Button -->
        <div class="d-flex align-items-center gap-3">
            <button type="submit" class="btn btn-primary">
                {{ __('Save') }}
            </button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-success small"
                >
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</section>
