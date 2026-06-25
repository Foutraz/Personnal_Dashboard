<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Register') }}</title>
</head>
<body>
    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf
        <label for="name">{{ __('Name') }}</label>
        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus>

        <label for="email">{{ __('Email') }}</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required>

        <label for="password">{{ __('Password') }}</label>
        <input id="password" type="password" name="password" required>

        <label for="password_confirmation">{{ __('Confirm Password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required>

        <button type="submit">{{ __('Register') }}</button>
    </form>

    <a href="{{ route('auth.google.redirect') }}">{{ __('Continue with Google') }}</a>
    <a href="{{ route('login') }}">{{ __('Log in') }}</a>
</body>
</html>
