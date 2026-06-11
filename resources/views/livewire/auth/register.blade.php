<x-layouts.brand-auth :title="__('Criar conta — DuoFund')">
    <div class="rise" style="animation-delay:.1s"
         x-data="{ show: false, showConfirm: false, pw: '', pwc: '' }">
        {{-- Cabeçalho --}}
        <div class="mb-7">
            <h2 class="font-display text-3xl font-600 tracking-tight text-gray-900">Vamos começar</h2>
            <p class="mt-1.5 text-sm text-gray-500">Crie sua conta — leva menos de um minuto.</p>
        </div>

        {{-- Convite encontrado --}}
        @if(session()->has('invite_family_id'))
            <div class="relative mb-6 overflow-hidden rounded-2xl border border-purple-200 bg-purple-50 p-4">
                <div class="flex items-start gap-3.5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-purple-600 text-white shadow-sm ring-4 ring-purple-100">
                        <i data-lucide="heart-handshake" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-purple-900">Convite encontrado!</h3>
                        <p class="mt-0.5 text-sm leading-relaxed text-purple-700">
                            Você está entrando numa família para gerenciar as finanças em dupla. Preencha seus dados para começar.
                        </p>
                    </div>
                </div>
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-purple-600/10 blur-2xl"></div>
            </div>
        @endif

        @if (session('status'))
            <div class="mb-6 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
                <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0"></i>
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf

            {{-- Nome --}}
            <div>
                <label for="name" class="mb-1.5 block text-sm font-semibold text-gray-700">Nome</label>
                <div class="relative">
                    <i data-lucide="user" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                    <input id="name" name="name" type="text" required autofocus autocomplete="name"
                           value="{{ old('name') }}" placeholder="Seu nome"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10
                                  @error('name') border-red-300 bg-red-50/50 @else border-gray-200 @enderror">
                </div>
                @error('name')
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>{{ $message }}</p>
                @enderror
            </div>

            {{-- E-mail --}}
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-gray-700">E-mail</label>
                <div class="relative">
                    <i data-lucide="mail" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                    <input id="email" name="email" type="email" required autocomplete="email"
                           value="{{ old('email') }}" placeholder="voce@email.com"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-4 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10
                                  @error('email') border-red-300 bg-red-50/50 @else border-gray-200 @enderror">
                </div>
                @error('email')
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>{{ $message }}</p>
                @enderror
            </div>

            {{-- Senha --}}
            <div>
                <label for="password" class="mb-1.5 block text-sm font-semibold text-gray-700">Senha</label>
                <div class="relative">
                    <i data-lucide="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="new-password"
                           x-model="pw" placeholder="••••••••"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-11 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10
                                  @error('password') border-red-300 bg-red-50/50 @else border-gray-200 @enderror">
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 transition hover:text-gray-600">
                        <i x-show="!show" data-lucide="eye" class="h-4 w-4"></i>
                        <i x-show="show" x-cloak data-lucide="eye-off" class="h-4 w-4"></i>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1.5 flex items-center gap-1 text-xs font-medium text-red-600"><i data-lucide="alert-circle" class="h-3.5 w-3.5"></i>{{ $message }}</p>
                @else
                    <p class="mt-1.5 flex items-center gap-1 text-xs transition-colors"
                       :class="pw.length >= 8 ? 'text-green-600' : 'text-gray-400'">
                        <i x-show="pw.length >= 8" data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                        <i x-show="pw.length < 8" data-lucide="info" class="h-3.5 w-3.5"></i>
                        Mínimo de 8 caracteres
                    </p>
                @enderror
            </div>

            {{-- Confirmar senha --}}
            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-gray-700">Confirmar senha</label>
                <div class="relative">
                    <i data-lucide="lock" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"></i>
                    <input id="password_confirmation" name="password_confirmation" :type="showConfirm ? 'text' : 'password'" required autocomplete="new-password"
                           x-model="pwc" placeholder="••••••••"
                           class="w-full rounded-xl border bg-gray-50/60 py-3 pl-11 pr-11 text-sm text-gray-900 placeholder-gray-400 transition
                                  focus:border-primary focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary/10 border-gray-200">
                    <button type="button" @click="showConfirm = !showConfirm" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md p-1 text-gray-400 transition hover:text-gray-600">
                        <i x-show="!showConfirm" data-lucide="eye" class="h-4 w-4"></i>
                        <i x-show="showConfirm" x-cloak data-lucide="eye-off" class="h-4 w-4"></i>
                    </button>
                </div>
                <p x-cloak x-show="pwc.length > 0" class="mt-1.5 flex items-center gap-1 text-xs font-medium transition-colors"
                   :class="pw === pwc ? 'text-green-600' : 'text-amber-600'">
                    <i x-show="pw === pwc" data-lucide="check-circle-2" class="h-3.5 w-3.5"></i>
                    <i x-show="pw !== pwc" data-lucide="alert-circle" class="h-3.5 w-3.5"></i>
                    <span x-text="pw === pwc ? 'As senhas coincidem' : 'As senhas ainda não coincidem'"></span>
                </p>
            </div>

            {{-- Botão --}}
            <button type="submit" data-test="register-user-button"
                    class="group flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-secondary hover:shadow-primary/40 active:scale-[.99]">
                Criar conta
                <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
            </button>
        </form>

        {{-- Link para login --}}
        <p class="mt-7 text-center text-sm text-gray-500">
            Já tem uma conta?
            <a href="{{ route('login') }}" class="font-semibold text-primary hover:text-secondary transition">Entrar</a>
        </p>
    </div>
</x-layouts.brand-auth>
