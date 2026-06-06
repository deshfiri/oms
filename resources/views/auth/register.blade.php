<x-guest-layout>
    <h1>Create account</h1>
    <p class="sub">Register a new staff account.</p>

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <div class="auth-field">
            <label class="auth-label" for="name">Name</label>
            <input class="auth-input" id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')<div class="auth-error">{{ $message }}</div>@enderror
        </div>
        <div class="auth-field">
            <label class="auth-label" for="email">Email</label>
            <input class="auth-input" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
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
        <div class="auth-row" style="justify-content:flex-end">
            <a class="auth-link" href="{{ route('login') }}">Already registered?</a>
        </div>
        <button class="auth-btn" type="submit">Register</button>
    </form>
</x-guest-layout>
