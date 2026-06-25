<x-layouts.guest :title="__('Login')">
    <x-ui.glass-card padding="p-8" class="reveal" style="animation-delay: 0.08s;">
        <div class="mb-7">
            <h1 class="font-display text-2xl font-bold tracking-tight">{{ __('Bon retour') }}</h1>
            <p class="mt-1 text-sm text-muted">{{ __('Connectez-vous à votre command deck.') }}</p>
        </div>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-violet/30 bg-violet-soft px-4 py-3 text-sm text-violet">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <div>
                <label for="email" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-faint">{{ __('Email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-xl border border-hairline bg-surface/60 px-4 py-3 text-sm text-ink transition placeholder:text-faint focus:border-cyan/50 focus:outline-none focus:ring-2 focus:ring-cyan/20"
                    placeholder="vous@exemple.com">
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-faint">{{ __('Password') }}</label>
                <input id="password" type="password" name="password" required
                    class="w-full rounded-xl border border-hairline bg-surface/60 px-4 py-3 text-sm text-ink transition placeholder:text-faint focus:border-cyan/50 focus:outline-none focus:ring-2 focus:ring-cyan/20"
                    placeholder="••••••••">
            </div>

            <label for="remember" class="flex items-center gap-2.5 text-sm text-muted">
                <input id="remember" type="checkbox" name="remember" value="1"
                    class="h-4 w-4 rounded border-hairline bg-surface text-cyan focus:ring-cyan/30">
                {{ __('Se souvenir de moi') }}
            </label>

            <x-ui.neon-button type="submit" variant="cyan" class="w-full">
                {{ __('Se connecter') }}
                <svg class="h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0-6-6m6 6-6 6"/></svg>
            </x-ui.neon-button>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wider text-faint">
            <span class="h-px flex-1 bg-hairline"></span>
            {{ __('ou') }}
            <span class="h-px flex-1 bg-hairline"></span>
        </div>

        <x-ui.neon-button :href="route('auth.google.redirect')" variant="ghost" class="w-full">
            <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="#EA4335" d="M12 5c1.6 0 3 .55 4.1 1.6L19 3.8C17.1 2 14.7 1 12 1 7.7 1 4 3.5 2.2 7.1l3.4 2.6C6.5 6.9 9 5 12 5Z"/><path fill="#4285F4" d="M23 12.3c0-.8-.1-1.5-.2-2.3H12v4.5h6.2c-.3 1.4-1.1 2.6-2.3 3.4l3.5 2.7C21.6 18.6 23 15.8 23 12.3Z"/><path fill="#FBBC05" d="M5.6 14.3c-.2-.7-.4-1.4-.4-2.3s.1-1.6.4-2.3L2.2 7.1C1.4 8.6 1 10.3 1 12s.4 3.4 1.2 4.9l3.4-2.6Z"/><path fill="#34A853" d="M12 23c2.7 0 5-.9 6.7-2.4l-3.5-2.7c-.9.6-2.1 1-3.2 1-3 0-5.5-1.9-6.4-4.6l-3.4 2.6C4 20.5 7.7 23 12 23Z"/></svg>
            {{ __('Continuer avec Google') }}
        </x-ui.neon-button>

        <p class="mt-6 text-center text-sm text-muted">
            {{ __('Pas encore de compte ?') }}
            <a href="{{ route('register') }}" class="font-medium text-cyan transition hover:text-glow-cyan">{{ __('Créer un compte') }}</a>
        </p>
    </x-ui.glass-card>
</x-layouts.guest>
