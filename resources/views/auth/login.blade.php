<x-guest-layout>
    <h1>Sign in</h1>
    <p class="sub">Welcome back. Enter your credentials to continue.</p>

    @if (session('status'))
        <div class="auth-status">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="auth-field">
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email')<div class="auth-error">{{ $message }}</div>@enderror
        </div>

        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input class="auth-input" id="password" type="password" name="password" required autocomplete="current-password">
            @error('password')<div class="auth-error">{{ $message }}</div>@enderror
        </div>

        <div class="auth-row">
            <label class="auth-check"><input type="checkbox" name="remember"> Remember me</label>
            @if (Route::has('password.request'))
                <a class="auth-link" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <button class="auth-btn" type="submit">Sign in</button>
    </form>
</x-guest-layout>
