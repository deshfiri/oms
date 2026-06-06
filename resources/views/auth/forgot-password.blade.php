<x-guest-layout>
    <h1>Reset password</h1>
    <p class="auth-note">Forgot your password? Enter your email and we'll send a reset link.</p>
    @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
    @endif
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="auth-field">
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <button class="auth-btn" type="submit">Email password reset link</button>
        <div class="auth-row" style="justify-content:center;margin-top:16px;margin-bottom:0">
            <a class="auth-link" href="{{ route('login') }}">&larr; Back to sign in</a>
        </div>
    </form>
</x-guest-layout>
