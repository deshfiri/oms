<x-guest-layout>
    <h1>Confirm password</h1>
    <p class="auth-note">This is a secure area. Please confirm your password before continuing.</p>
    <form method="POST" action="{{ route('password.confirm') }}">
        @csrf
        <div class="auth-field">
            <label class="auth-label" for="password">Password</label>
            <input class="auth-input" id="password" type="password" name="password" required autocomplete="current-password" autofocus>
            @error('password')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <button class="auth-btn" type="submit">Confirm</button>
    </form>
</x-guest-layout>
