<x-guest-layout>
    <div class="mb-4 text-muted small">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 alert alert-success small" role="alert">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 d-flex align-items-center justify-content-between">
        <!-- Resend Verification Email -->
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary">
                {{ __('Resend Verification Email') }}
            </button>
        </form>

        <!-- Log Out -->
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-link text-decoration-none text-muted small">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
