<x-guest-layout>
    <h1>Verify your email</h1>
    <p class="auth-note">Thanks for signing up. Please verify your email by clicking the link we just emailed you. If you didn't get it, we'll gladly send another.</p>
    @if (session('status') == 'verification-link-sent')
        <div class="auth-status">A new verification link has been sent to your email address.</div>
    @endif
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <button class="auth-btn" type="submit">Resend verification email</button>
    </form>
    <form method="POST" action="{{ route('logout') }}" style="margin-top:14px">
        @csrf
        <div class="auth-row" style="justify-content:center;margin:0">
            <button type="submit" class="auth-link" style="background:none;border:none;cursor:pointer;font-family:inherit">Log out</button>
        </div>
    </form>
</x-guest-layout>
