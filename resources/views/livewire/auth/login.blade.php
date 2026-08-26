<x-layouts.brand-auth :title="__('Entrar — DuoFund')">
    <div class="rise" style="animation-delay:.1s">
        {{-- Cabeçalho --}}
        <div class="mb-7">
            <h2 class="font-display text-3xl font-600 tracking-tight text-gray-900">Que bom te ver!</h2>
            <p class="mt-1.5 text-sm text-gray-500">Entre na sua conta para continuar cuidando das finanças.</p>
        </div>

        {{-- Convite pendente --}}
        @if(session()->has('invite_family_id'))
            <div class="relative mb-6 overflow-hidden rounded-2xl border border-purple-200 bg-purple-50 p-4">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow-sm ring-4 ring-purple-100">
                        <x-lucide-heart-handshake class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="font-bold text-purple-900">Convite pendente!</h3>
                        <p class="mt-0.5 text-sm leading-relaxed text-purple-700">
                            Entre para juntar-se à família e gerenciar as finanças em dupla.
                            <a href="{{ route('register') }}" class="font-semibold underline decoration-purple-300 underline-offset-2 hover:text-purple-900">Ou crie uma conta nova</a>.
                        </p>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-purple-600/10 blur-2xl"></div>
            </div>
        @endif

        {{-- Status de sessão (ex: link de redefinição enviado) --}}
        @if (session('status'))
            <div class="mb-6 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                <x-lucide-check-circle-2 class="h-4 w-4 shrink-0" />
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-5" x-data="{ show: false }">
            @csrf

            {{-- E-mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-gray-700">E-mail</label>
                <div class="relative">
                    <x-lucide-mail class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input id="email" name="email" type="email" required autofocus autocomplete="email"
                           value="{{ old('email') }}" placeholder="voce@email.com"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10
                                  @error('email') border-red-300 bg-red-50/50 @else border-gray-200 @enderror">
                </div>
                @error('email')
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600"><x-lucide-alert-circle class="h-3.5 w-3.5" />{{ $message }}</p>
                @enderror
            </div>

            {{-- Senha --}}
            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Senha</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-xs font-semibold text-primary hover:text-secondary transition">Esqueceu?</a>
                    @endif
                </div>
                <div class="relative">
                    <x-lucide-lock class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-11 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10
                                  @error('password') border-red-300 bg-red-50/50 @else border-gray-200 @enderror">
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 transition hover:text-gray-600"
                            :aria-label="show ? 'Ocultar senha' : 'Mostrar senha'">
                        <x-lucide-eye x-show="!show" class="h-4 w-4" />
                        <x-lucide-eye-off x-show="show" x-cloak class="h-4 w-4" />
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600"><x-lucide-alert-circle class="h-3.5 w-3.5" />{{ $message }}</p>
                @enderror
            </div>

            {{-- Lembrar de mim --}}
            <label class="flex cursor-pointer items-center gap-2.5 select-none">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}
                       class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary/30 focus:ring-offset-0">
                <span class="text-sm text-gray-600">Manter conectado</span>
            </label>

            {{-- Botão --}}
            <button type="submit" data-test="login-button"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-secondary hover:shadow-primary/40 active:scale-[.99]">
                Entrar
                <x-lucide-arrow-right class="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
            </button>
        </form>

        {{-- Link para cadastro --}}
        @if (Route::has('register'))
            <p class="mt-7 text-center text-sm text-gray-500">
                Ainda não tem conta?
                <a href="{{ route('register') }}" class="font-semibold text-primary hover:text-secondary transition">Criar conta</a>
            </p>
        @endif
    </div>
</x-layouts.brand-auth>
