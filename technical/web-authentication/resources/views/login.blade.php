<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Login') }}</title>
</head>
<body>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">{{ __('Email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>

        <label for="password">{{ __('Password') }}</label>
        <input id="password" type="password" name="password" required>

        <label for="remember">
            <input id="remember" type="checkbox" name="remember" value="1">
            {{ __('Remember me') }}
        </label>

        <button type="submit">{{ __('Log in') }}</button>
    </form>

    <a href="{{ route('auth.google.redirect') }}">{{ __('Continue with Google') }}</a>
    <a href="{{ route('register') }}">{{ __('Register') }}</a>
</body>
</html>
