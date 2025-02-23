<x-guest-layout>
    <div class="alert alert-info mb-4 small">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </div>

    <!-- Session Status -->
    @if (session('status'))
        <div class="alert alert-success mb-4 small">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div class="mb-3">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input 
                id="email" 
                type="email" 
                name="email" 
                class="form-control" 
                value="{{ old('email') }}" 
                required 
                autofocus
            />
            @if ($errors->has('email'))
                <div class="invalid-feedback">
                    {{ $errors->first('email') }}
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end mt-3">
            <button type="submit" class="btn btn-primary">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
</x-guest-layout>
