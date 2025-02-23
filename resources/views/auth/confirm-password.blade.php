<x-guest-layout>
    <div class="alert alert-info mb-4 small">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">{{ __('Password') }}</label>
            <input 
                id="password" 
                type="password" 
                name="password" 
                class="form-control" 
                required 
                autocomplete="current-password"
            />
            @if ($errors->has('password'))
                <div class="invalid-feedback">
                    {{ $errors->first('password') }}
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">
                {{ __('Confirm') }}
            </button>
        </div>
    </form>
</x-guest-layout>
