<x-guest-layout>
    <h1>Set a new password</h1>
    <p class="sub">Choose a new password for your account.</p>
    <form method="POST" action="{{ route('password.store') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="auth-field">
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username">
            @error('email')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input class="auth-input" id="password" type="password" name="password" required autocomplete="new-password">
            @error('password')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <div class="auth-field">
            <label class="auth-label" for="password_confirmation">Confirm password</label>
            <input class="auth-input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            @error('password_confirmation')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <button class="auth-btn" type="submit">Reset password</button>
    </form>
</x-guest-layout>
