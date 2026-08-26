<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'DuoFund — Finanças a Dois' }}</title>
    @include('partials.favicon')


    @vite('resources/css/duofund.css')

    @vite('resources/js/landing.js')
</head>
<body class="min-h-screen bg-[#eef2f7] text-gray-900 antialiased">
    <div class="min-h-screen lg:grid lg:grid-cols-[1.05fr_1fr] xl:grid-cols-2">

        {{-- ============ PAINEL DA MARCA (desktop) ============ --}}
        <aside class="grain relative hidden lg:flex flex-col justify-between overflow-hidden p-12 xl:p-16
                      bg-gradient-to-br from-[#1e63c4] via-primary to-[#1746a0] text-white">
            {{-- Brilhos decorativos --}}
            <div class="drift absolute -top-24 -left-20 h-96 w-96 rounded-full bg-secondary/40 blur-3xl"></div>
            <div class="drift absolute bottom-0 right-0 h-80 w-80 rounded-full bg-accent/20 blur-3xl" style="animation-delay:-7s"></div>
            <div class="absolute top-1/3 right-10 h-40 w-40 rounded-full bg-white/5 blur-2xl"></div>

            {{-- Topo: logo --}}
            <div class="relative rise" style="animation-delay:.05s">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25 shadow-lg">
                        <span class="text-base font-black tracking-tighter leading-none">DF</span>
                    </div>
                    <span class="text-xl font-bold tracking-tight">DuoFund</span>
                </a>
            </div>

            {{-- Centro: headline + motivo casal --}}
            <div class="relative max-w-md">
                {{-- Dois avatares sobrepostos = casal --}}
                <div class="mb-8 flex -space-x-3 rise" style="animation-delay:.15s">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white text-primary font-bold text-lg ring-4 ring-primary/30 shadow-xl">J</div>
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-accent text-white font-bold text-lg ring-4 ring-primary/30 shadow-xl">M</div>
                </div>

                <h1 class="font-display text-[2.6rem] xl:text-5xl font-600 leading-[1.05] tracking-tight rise" style="animation-delay:.2s">
                    Suas finanças,<br>
                    <span class="italic text-accent">a dois.</span>
                </h1>
                <p class="mt-5 text-base text-blue-100/90 leading-relaxed rise" style="animation-delay:.3s">
                    Organizem juntos o dinheiro do casal — com transparência sobre o que é seu e o que é de vocês.
                </p>

                {{-- Bullets de valor --}}
                <div class="mt-10 space-y-4">
                    @foreach([
                        ['heart', 'Pessoal & Compartilhado', 'Separe o seu dinheiro do dinheiro do casal.'],
                        ['target', 'Metas em conjunto', 'Sonhem e poupem para os objetivos de vocês.'],
                        ['pie-chart', 'Orçamento sob controle', 'Acompanhem gastos por categoria, mês a mês.'],
                    ] as $i => $b)
                        <div class="flex items-start gap-3.5 rise" style="animation-delay:{{ 0.4 + $i * 0.1 }}s">
                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/12 ring-1 ring-white/15 backdrop-blur-sm">
                                <x-dynamic-component :component="'lucide-'.($b[0])" class="h-4 w-4 text-accent" />
                            </div>
                            <div>
                                <p class="font-semibold leading-tight">{{ $b[1] }}</p>
                                <p class="text-sm text-blue-100/75 leading-snug">{{ $b[2] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Rodapé --}}
            <p class="relative text-xs text-blue-100/60 rise" style="animation-delay:.75s">
                © {{ date('Y') }} DuoFund · Gerenciando finanças juntos.
            </p>
        </aside>

        {{-- ============ ÁREA DO FORMULÁRIO ============ --}}
        <main class="relative flex min-h-screen items-center justify-center px-5 py-10 sm:px-8 lg:py-12">
            {{-- Faixa de marca no topo (mobile) --}}
            <div class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r from-primary via-secondary to-accent lg:hidden"></div>

            <div class="w-full max-w-md">
                {{-- Logo (mobile) --}}
                <div class="mb-8 flex items-center justify-center gap-2.5 lg:hidden rise">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary shadow-md shadow-primary/25">
                        <span class="text-sm font-black tracking-tighter leading-none text-white">DF</span>
                    </div>
                    <span class="text-lg font-bold tracking-tight"><span class="text-primary">Duo</span>Fund</span>
                </div>

                {{ $slot }}
            </div>
        </main>
    </div>

</body>
</html>
