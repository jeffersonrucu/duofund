<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>DuoFund — Finanças a dois, sem complicação</title>
    <meta name="description" content="O app de finanças para casais. Organizem juntos o dinheiro de vocês — com transparência sobre o que é seu e o que é compartilhado.">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: { extend: {
                colors: { primary: '#2674D9', secondary: '#4184DD', accent: '#E2B93B' },
                fontFamily: { sans: ['DM Sans','sans-serif'], display: ['Fraunces','serif'] }
            } }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,ital,wght@9..144,0,400;9..144,0,500;9..144,0,600;9..144,1,500;9..144,1,600&display=swap');
        body { font-family: 'DM Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        html { scroll-behavior: smooth; }

        @keyframes rise { from { opacity:0; transform: translateY(18px); } to { opacity:1; transform: translateY(0); } }
        .rise { opacity:0; animation: rise .7s cubic-bezier(.16,1,.3,1) forwards; }

        @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .float { animation: float 6s ease-in-out infinite; }

        @keyframes drift { 0%,100% { transform: translate(0,0) scale(1); } 50% { transform: translate(24px,-20px) scale(1.1); } }
        .drift { animation: drift 16s ease-in-out infinite; }

        .reveal { opacity:0; transform: translateY(28px); transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1); }
        .reveal.in { opacity:1; transform: none; }

        .grain::before {
            content:''; position:absolute; inset:0; pointer-events:none; opacity:.05;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 250 250' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
    </style>
</head>
<body class="bg-[#eef2f7] text-gray-900 antialiased">

    {{-- ===================== NAV ===================== --}}
    <header x-data="{ open: false, scrolled: false }" @scroll.window="scrolled = window.scrollY > 20"
            class="fixed inset-x-0 top-0 z-50 transition-all duration-300"
            :class="scrolled ? 'bg-white/85 backdrop-blur-lg border-b border-gray-200/70 shadow-sm' : ''">
        <nav class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="#top" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-md shadow-primary/25">
                    <span class="text-xs font-black tracking-tighter leading-none text-white">DF</span>
                </div>
                <span class="text-lg font-bold tracking-tight"><span class="text-primary">Duo</span>Fund</span>
            </a>

            <div class="hidden items-center gap-8 md:flex">
                <a href="#recursos" class="text-sm font-medium text-gray-600 transition hover:text-primary">Recursos</a>
                <a href="#como-funciona" class="text-sm font-medium text-gray-600 transition hover:text-primary">Como funciona</a>
                <a href="#conceito" class="text-sm font-medium text-gray-600 transition hover:text-primary">Pessoal vs. Casal</a>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ route('login') }}" class="hidden rounded-lg px-4 py-2 text-sm font-semibold text-gray-600 transition hover:text-primary sm:inline-block">Entrar</a>
                <a href="{{ route('register') }}"
                   class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/25 transition hover:bg-secondary">
                    Criar conta
                </a>
                <button @click="open = !open" class="md:hidden p-2 text-gray-600" aria-label="Menu">
                    <i x-show="!open" data-lucide="menu" class="h-5 w-5"></i>
                    <i x-show="open" x-cloak data-lucide="x" class="h-5 w-5"></i>
                </button>
            </div>
        </nav>

        {{-- Menu mobile --}}
        <div x-show="open" x-cloak x-collapse class="border-t border-gray-100 bg-white md:hidden">
            <div class="flex flex-col px-5 py-3">
                <a href="#recursos" @click="open=false" class="py-2.5 text-sm font-medium text-gray-600">Recursos</a>
                <a href="#como-funciona" @click="open=false" class="py-2.5 text-sm font-medium text-gray-600">Como funciona</a>
                <a href="#conceito" @click="open=false" class="py-2.5 text-sm font-medium text-gray-600">Pessoal vs. Casal</a>
                <a href="{{ route('login') }}" class="mt-1 py-2.5 text-sm font-semibold text-primary">Entrar →</a>
            </div>
        </div>
    </header>

    {{-- ===================== HERO ===================== --}}
    <section id="top" class="grain relative overflow-hidden pt-32 pb-20 sm:pt-40 sm:pb-28">
        <div class="drift absolute -top-32 -left-32 h-[28rem] w-[28rem] rounded-full bg-primary/15 blur-3xl"></div>
        <div class="drift absolute top-20 right-0 h-96 w-96 rounded-full bg-accent/10 blur-3xl" style="animation-delay:-8s"></div>

        <div class="relative mx-auto grid max-w-6xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-2">
            {{-- Texto --}}
            <div>
                <div class="rise inline-flex items-center gap-2 rounded-full border border-primary/15 bg-white/70 px-3.5 py-1.5 text-xs font-semibold text-primary backdrop-blur-sm" style="animation-delay:.05s">
                    <span class="flex -space-x-1.5">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[8px] font-bold text-white ring-2 ring-white">J</span>
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-accent text-[8px] font-bold text-white ring-2 ring-white">M</span>
                    </span>
                    Feito para casais
                </div>

                <h1 class="rise mt-5 font-display text-5xl font-600 leading-[1.04] tracking-tight sm:text-6xl" style="animation-delay:.15s">
                    Finanças <span class="italic text-primary">a dois</span>,<br>sem complicação.
                </h1>

                <p class="rise mt-6 max-w-md text-lg leading-relaxed text-gray-600" style="animation-delay:.25s">
                    Organizem juntos o dinheiro de vocês — com transparência sobre o que é <strong class="text-gray-800">seu</strong> e o que é <strong class="text-gray-800">do casal</strong>. Orçamento, metas e desejos no mesmo lugar.
                </p>

                <div class="rise mt-8 flex flex-col gap-3 sm:flex-row" style="animation-delay:.35s">
                    <a href="{{ route('register') }}"
                       class="group flex items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:bg-secondary hover:shadow-primary/40 active:scale-[.99]">
                        Começar de graça
                        <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
                    </a>
                    <a href="#como-funciona"
                       class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-6 py-3.5 text-sm font-semibold text-gray-700 transition hover:border-gray-300 hover:bg-gray-50">
                        <i data-lucide="play-circle" class="h-4 w-4 text-primary"></i>
                        Ver como funciona
                    </a>
                </div>

                <div class="rise mt-7 flex items-center gap-5 text-xs text-gray-500" style="animation-delay:.45s">
                    <span class="flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-green-500"></i> Grátis para começar</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="check" class="h-3.5 w-3.5 text-green-500"></i> Sem cartão</span>
                </div>
            </div>

            {{-- Mockup do produto --}}
            <div class="rise relative" style="animation-delay:.3s">
                <div class="float relative mx-auto max-w-sm">
                    {{-- Card principal: resumo do mês --}}
                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-2xl shadow-primary/10">
                        <div class="mb-4 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary"><span class="text-[10px] font-black text-white">DF</span></div>
                                <span class="text-sm font-bold">Junho 2026</span>
                            </div>
                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-[10px] font-semibold text-green-600">Mês atual</span>
                        </div>

                        <div class="mb-4 grid grid-cols-3 divide-x divide-gray-100 rounded-xl bg-gray-50/70 py-3">
                            <div class="px-2 text-center"><p class="text-[9px] uppercase tracking-wide text-gray-400">Entradas</p><p class="text-xs font-bold text-green-600">R$ 9.700</p></div>
                            <div class="px-2 text-center"><p class="text-[9px] uppercase tracking-wide text-gray-400">Saídas</p><p class="text-xs font-bold text-red-500">R$ 4.140</p></div>
                            <div class="px-2 text-center"><p class="text-[9px] uppercase tracking-wide text-gray-400">Saldo</p><p class="text-xs font-bold text-primary">R$ 5.560</p></div>
                        </div>

                        {{-- Categorias --}}
                        <div class="space-y-3">
                            @foreach([['Mercado','#2674D9',72],['Moradia','#7c3aed',88],['Lazer','#E2B93B',45]] as $c)
                                <div>
                                    <div class="mb-1 flex justify-between text-[11px]"><span class="font-medium text-gray-700">{{ $c[0] }}</span><span class="text-gray-400">{{ $c[2] }}%</span></div>
                                    <div class="h-2 overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-2 rounded-full" style="width: {{ $c[2] }}%; background: {{ $c[1] }}"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Card flutuante: meta --}}
                    <div class="float absolute -bottom-6 -left-6 w-44 rounded-2xl border border-gray-100 bg-white p-3.5 shadow-xl" style="animation-delay:-3s">
                        <div class="flex items-center gap-2">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-50"><i data-lucide="target" class="h-4 w-4 text-orange-500"></i></div>
                            <div><p class="text-[11px] font-bold leading-tight">Viagem Europa</p><p class="text-[9px] text-gray-400">34% guardado</p></div>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100"><div class="h-1.5 rounded-full bg-orange-400" style="width:34%"></div></div>
                    </div>

                    {{-- Badge flutuante: casal --}}
                    <div class="float absolute -right-4 -top-4 rounded-2xl border border-purple-100 bg-white px-3 py-2 shadow-xl" style="animation-delay:-1.5s">
                        <div class="flex items-center gap-1.5">
                            <span class="flex -space-x-2">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary text-[9px] font-bold text-white ring-2 ring-white">J</span>
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-purple-500 text-[9px] font-bold text-white ring-2 ring-white">M</span>
                            </span>
                            <span class="text-[10px] font-semibold text-purple-700">Nosso dinheiro</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== RECURSOS ===================== --}}
    <section id="recursos" class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
        <div class="reveal mx-auto mb-14 max-w-xl text-center">
            <p class="text-sm font-bold uppercase tracking-widest text-primary">Recursos</p>
            <h2 class="mt-3 font-display text-4xl font-600 tracking-tight">Tudo que o casal precisa</h2>
            <p class="mt-4 text-gray-600">Da conta do mercado à viagem dos sonhos — num app pensado para duas pessoas.</p>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $features = [
                    ['arrow-left-right','Transações','blue','Registre entradas e saídas, recorrentes ou avulsas, em segundos.'],
                    ['credit-card','Parcelas','amber','Acompanhe compras parceladas e saiba o quanto ainda falta quitar.'],
                    ['pie-chart','Orçamento','emerald','Defina limites por categoria e veja o consumo em tempo real.'],
                    ['target','Metas','orange','Poupem juntos para objetivos e acompanhem o progresso.'],
                    ['gift','Lista de desejos','pink','Anote o que querem comprar e simulem o impacto no bolso.'],
                    ['bar-chart-3','Relatórios','slate','Extrato mensal completo, pronto para imprimir ou salvar em PDF.'],
                ];
                $tones = [
                    'blue'    => 'bg-blue-50 text-blue-600',
                    'amber'   => 'bg-amber-50 text-amber-600',
                    'emerald' => 'bg-emerald-50 text-emerald-600',
                    'orange'  => 'bg-orange-50 text-orange-600',
                    'pink'    => 'bg-pink-50 text-pink-600',
                    'slate'   => 'bg-slate-100 text-slate-600',
                ];
            @endphp
            @foreach($features as $i => $f)
                <div class="reveal group rounded-2xl border border-gray-100 bg-white p-6 transition hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-200/60" style="transition-delay: {{ $i * 60 }}ms">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl {{ $tones[$f[2]] }} transition group-hover:scale-105">
                        <i data-lucide="{{ $f[0] }}" class="h-6 w-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900">{{ $f[1] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $f[3] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===================== CONCEITO: PESSOAL vs CASAL ===================== --}}
    <section id="conceito" class="relative overflow-hidden bg-gradient-to-br from-[#1e63c4] via-primary to-[#1746a0] py-20 text-white sm:py-28">
        <div class="grain absolute inset-0"></div>
        <div class="drift absolute -bottom-24 -right-24 h-96 w-96 rounded-full bg-accent/15 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-5 sm:px-8">
            <div class="reveal mx-auto mb-14 max-w-xl text-center">
                <p class="text-sm font-bold uppercase tracking-widest text-accent">O conceito</p>
                <h2 class="mt-3 font-display text-4xl font-600 tracking-tight">Seu dinheiro. <span class="italic">O dinheiro de vocês.</span></h2>
                <p class="mt-4 text-blue-100/90">Cada lançamento tem um escopo. Você decide o que fica privado e o que é compartilhado com seu par.</p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div class="reveal rounded-3xl border border-white/15 bg-white/10 p-7 backdrop-blur-sm">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-white/15"><i data-lucide="user" class="h-6 w-6"></i></div>
                    <h3 class="text-xl font-bold">Meu Dinheiro</h3>
                    <p class="mt-2 text-sm leading-relaxed text-blue-100/80">Suas finanças pessoais — só você vê. Salário, gastos individuais e metas privadas ficam reservados.</p>
                </div>
                <div class="reveal rounded-3xl border border-white/15 bg-white/10 p-7 backdrop-blur-sm" style="transition-delay:80ms">
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-accent/25"><i data-lucide="users" class="h-6 w-6 text-accent"></i></div>
                    <h3 class="text-xl font-bold">Nosso Dinheiro</h3>
                    <p class="mt-2 text-sm leading-relaxed text-blue-100/80">As finanças do casal — ambos veem e contribuem. Contas da casa, metas conjuntas e desejos compartilhados.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===================== COMO FUNCIONA ===================== --}}
    <section id="como-funciona" class="mx-auto max-w-6xl px-5 py-20 sm:px-8 sm:py-28">
        <div class="reveal mx-auto mb-14 max-w-xl text-center">
            <p class="text-sm font-bold uppercase tracking-widest text-primary">Como funciona</p>
            <h2 class="mt-3 font-display text-4xl font-600 tracking-tight">Comecem em 3 passos</h2>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            @php
                $steps = [
                    ['1','user-plus','Crie sua conta','Ao se cadastrar, uma "família" é criada automaticamente para você.'],
                    ['2','heart-handshake','Convide seu par','Gere um link de convite no modo "Nosso Dinheiro" e envie para seu parceiro(a).'],
                    ['3','wallet','Comecem a registrar','Lancem transações, definam orçamentos e criem metas — juntos.'],
                ];
            @endphp
            @foreach($steps as $i => $s)
                <div class="reveal relative rounded-2xl border border-gray-100 bg-white p-7" style="transition-delay: {{ $i * 80 }}ms">
                    <span class="absolute right-6 top-5 font-display text-5xl font-600 text-gray-100">{{ $s[0] }}</span>
                    <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary"><i data-lucide="{{ $s[1] }}" class="h-6 w-6"></i></div>
                    <h3 class="text-lg font-bold">{{ $s[2] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $s[3] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ===================== CTA FINAL ===================== --}}
    <section class="mx-auto max-w-6xl px-5 pb-24 sm:px-8">
        <div class="reveal relative overflow-hidden rounded-3xl bg-gray-900 px-8 py-14 text-center sm:py-20">
            <div class="drift absolute -left-20 -top-20 h-72 w-72 rounded-full bg-primary/30 blur-3xl"></div>
            <div class="drift absolute -bottom-20 -right-20 h-72 w-72 rounded-full bg-accent/20 blur-3xl" style="animation-delay:-6s"></div>
            <div class="relative">
                <div class="mb-6 flex justify-center -space-x-3">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-primary text-lg font-bold text-white ring-4 ring-gray-900">J</span>
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-accent text-lg font-bold text-white ring-4 ring-gray-900">M</span>
                </div>
                <h2 class="font-display text-4xl font-600 tracking-tight text-white sm:text-5xl">Prontos para começar juntos?</h2>
                <p class="mx-auto mt-4 max-w-md text-gray-400">Criem a conta de vocês e deixem a planilha de lado de uma vez.</p>
                <a href="{{ route('register') }}"
                   class="group mt-8 inline-flex items-center justify-center gap-2 rounded-xl bg-white px-7 py-3.5 text-sm font-semibold text-gray-900 shadow-lg transition hover:bg-gray-100 active:scale-[.99]">
                    Criar conta grátis
                    <i data-lucide="arrow-right" class="h-4 w-4 transition-transform group-hover:translate-x-0.5"></i>
                </a>
            </div>
        </div>
    </section>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="border-t border-gray-200/70 bg-white">
        <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-5 py-8 sm:flex-row sm:px-8">
            <a href="#top" class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary"><span class="text-[10px] font-black text-white">DF</span></div>
                <span class="font-bold tracking-tight"><span class="text-primary">Duo</span>Fund</span>
            </a>
            <p class="text-xs text-gray-400">© {{ date('Y') }} DuoFund · Gerenciando finanças juntos.</p>
            <div class="flex items-center gap-5 text-sm font-medium text-gray-500">
                <a href="{{ route('privacy') }}" class="transition hover:text-primary">Privacidade</a>
                <a href="{{ route('login') }}" class="transition hover:text-primary">Entrar</a>
                <a href="{{ route('register') }}" class="transition hover:text-primary">Criar conta</a>
            </div>
        </div>
    </footer>

    <script>
        // Lucide antes do Alpine (defer), para preservar x-show/@click nos <svg>
        if (window.lucide) lucide.createIcons();

        // Reveal on scroll
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        document.querySelectorAll('.reveal').forEach(el => io.observe(el));
    </script>
</body>
</html>
