<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="alert alert-info mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input
                id="email"
                class="form-control mt-1"
                type="email"
                name="email"
                :value="old('email')"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="text-danger mt-2" />
        </div>

        <!-- Password -->
        <div class="mb-3">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input
                id="password"
                class="form-control mt-1"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="text-danger mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="form-check mb-3">
            <input
                id="remember_me"
                type="checkbox"
                class="form-check-input"
                name="remember"
            />
            <label for="remember_me" class="form-check-label text-sm text-gray-600">
                {{ __('Remember me') }}
            </label>
        </div>

        <!-- Login Actions -->
        <div class="d-flex justify-content-between align-items-center">
            @if (Route::has('password.request'))
                <a
                    href="{{ route('password.request') }}"
                    class="text-decoration-none small text-muted"
                >
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="btn btn-primary">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
