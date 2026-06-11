<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Política de Privacidade — DuoFund</title>
    <meta name="description" content="Como o DuoFund coleta, usa e protege os dados financeiros do casal.">
    @include('partials.favicon')

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

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&family=Fraunces:opsz,ital,wght@9..144,0,400;9..144,0,500;9..144,0,600;9..144,1,500;9..144,1,600&display=swap');
        body { font-family: 'DM Sans', sans-serif; }
        .grain::before {
            content:''; position:absolute; inset:0; pointer-events:none; opacity:.05;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 250 250' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
        /* Âncoras não ficam escondidas sob o header fixo */
        .doc h2 { scroll-margin-top: 6rem; }
        html { scroll-behavior: smooth; }
    </style>
</head>
<body class="bg-[#eef2f7] text-gray-900 antialiased">

    {{-- ===================== NAV ===================== --}}
    <header class="sticky top-0 z-50 border-b border-gray-200/70 bg-white/85 backdrop-blur-lg">
        <nav class="mx-auto flex max-w-5xl items-center justify-between px-5 py-4 sm:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary shadow-md shadow-primary/25">
                    <span class="text-xs font-black tracking-tighter leading-none text-white">DF</span>
                </div>
                <span class="text-lg font-bold tracking-tight"><span class="text-primary">Duo</span>Fund</span>
            </a>
            <a href="{{ url('/') }}" class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-gray-600 transition hover:text-primary">
                <i data-lucide="arrow-left" class="h-4 w-4"></i> Voltar
            </a>
        </nav>
    </header>

    {{-- ===================== HERO ===================== --}}
    <section class="grain relative overflow-hidden border-b border-gray-200/70 bg-gradient-to-br from-[#1e63c4] via-primary to-[#1746a0] text-white">
        <div class="absolute -top-24 -left-20 h-80 w-80 rounded-full bg-secondary/30 blur-3xl"></div>
        <div class="relative mx-auto max-w-5xl px-5 py-16 sm:px-8 sm:py-20">
            <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold backdrop-blur-sm">
                <i data-lucide="shield-check" class="h-3.5 w-3.5 text-accent"></i> Sua privacidade
            </div>
            <h1 class="font-display text-4xl font-semibold tracking-tight sm:text-5xl">Política de Privacidade</h1>
            <p class="mt-3 max-w-xl text-blue-100/90">
                Como coletamos, usamos e protegemos os dados que você e seu par confiam ao DuoFund.
            </p>
            <p class="mt-4 text-xs text-blue-100/70">Última atualização: 10 de junho de 2026</p>
        </div>
    </section>

    {{-- ===================== CONTEÚDO ===================== --}}
    <main class="mx-auto max-w-5xl px-5 py-12 sm:px-8 sm:py-16">
        <div class="grid gap-10 lg:grid-cols-[220px_1fr]">

            {{-- Índice --}}
            <aside class="hidden lg:block">
                <nav class="sticky top-24 rounded-2xl border border-gray-100 bg-white p-4 text-sm">
                    <p class="mb-2 px-2 text-xs font-bold uppercase tracking-wider text-gray-400">Nesta página</p>
                    @php
                        $toc = [
                            'intro' => 'Introdução',
                            'dados' => 'Dados que coletamos',
                            'uso' => 'Como usamos',
                            'casal' => 'Compartilhamento com o casal',
                            'armazenamento' => 'Armazenamento e segurança',
                            'cookies' => 'Cookies e sessão',
                            'direitos' => 'Seus direitos (LGPD)',
                            'retencao' => 'Retenção e exclusão',
                            'alteracoes' => 'Alterações',
                            'contato' => 'Contato',
                        ];
                    @endphp
                    @foreach($toc as $id => $label)
                        <a href="#{{ $id }}" class="block rounded-lg px-2 py-1.5 text-gray-600 transition hover:bg-gray-50 hover:text-primary">{{ $label }}</a>
                    @endforeach
                </nav>
            </aside>

            {{-- Texto --}}
            <article class="doc rounded-2xl border border-gray-100 bg-white p-6 sm:p-10
                            [&_h2]:font-display [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:tracking-tight [&_h2]:text-gray-900
                            [&_h2]:mt-10 [&_h2]:mb-3 [&_h2:first-child]:mt-0
                            [&_p]:text-[15px] [&_p]:leading-relaxed [&_p]:text-gray-600 [&_p]:mb-4
                            [&_ul]:mb-4 [&_ul]:space-y-2 [&_li]:text-[15px] [&_li]:leading-relaxed [&_li]:text-gray-600
                            [&_strong]:text-gray-800 [&_a]:font-semibold [&_a]:text-primary hover:[&_a]:text-secondary">

                <p class="rounded-xl border border-blue-100 bg-blue-50/60 p-4 !text-blue-800">
                    <strong>Resumo rápido:</strong> coletamos o mínimo necessário para o app funcionar (seu nome, e-mail e os lançamentos
                    financeiros que você registra). Não vendemos seus dados. O que é marcado como <strong>pessoal</strong> só você vê;
                    o que é <strong>compartilhado</strong> fica visível para o seu par na mesma família.
                </p>

                <h2 id="intro">1. Introdução</h2>
                <p>
                    O DuoFund é um aplicativo de finanças para casais. Esta Política explica como tratamos seus dados pessoais quando
                    você usa o app, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD). Ao criar uma
                    conta, você concorda com as práticas descritas aqui.
                </p>

                <h2 id="dados">2. Dados que coletamos</h2>
                <ul class="list-disc pl-5">
                    <li><strong>Dados de cadastro:</strong> nome e endereço de e-mail.</li>
                    <li><strong>Credenciais:</strong> sua senha, sempre armazenada de forma criptografada (hash) — nunca em texto puro.</li>
                    <li><strong>Dados financeiros que você insere:</strong> transações (receitas, despesas, parcelas), categorias e limites de orçamento, metas, e itens da lista de desejos, cada um com seu escopo (pessoal ou compartilhado).</li>
                    <li><strong>Dados de uso:</strong> informações técnicas como data/hora de acesso e preferências de visualização salvas em sessão.</li>
                </ul>
                <p>Não coletamos dados de cartão de crédito nem nos conectamos automaticamente a contas bancárias — todos os valores são informados manualmente por você.</p>

                <h2 id="uso">3. Como usamos seus dados</h2>
                <ul class="list-disc pl-5">
                    <li>Fornecer e operar as funcionalidades do app (resumos, orçamentos, metas, relatórios).</li>
                    <li>Autenticar seu acesso e manter sua conta segura.</li>
                    <li>Exibir e calcular suas finanças pessoais e as finanças compartilhadas do casal.</li>
                    <li>Melhorar a estabilidade e a experiência de uso do produto.</li>
                </ul>
                <p>Não usamos seus dados financeiros para publicidade e <strong>não vendemos nem alugamos</strong> seus dados a terceiros.</p>

                <h2 id="casal">4. Compartilhamento com o casal</h2>
                <p>
                    O DuoFund é feito para duas pessoas. Quando você entra em uma <strong>família</strong> (via link de convite), os
                    registros marcados como <strong>compartilhados</strong> ("Nosso Dinheiro") passam a ser visíveis para o outro
                    membro da família. Já os registros marcados como <strong>pessoais</strong> ("Meu Dinheiro") permanecem privados e
                    visíveis apenas para você.
                </p>
                <p>
                    Cada família comporta no máximo dois membros. Você é responsável por convidar apenas pessoas de sua confiança,
                    já que o compartilhamento expõe os dados de escopo compartilhado ao parceiro(a).
                </p>

                <h2 id="armazenamento">5. Armazenamento e segurança</h2>
                <p>
                    Seus dados são armazenados em banco de dados em servidor seguro. Adotamos medidas técnicas como criptografia de
                    senhas, transmissão via HTTPS e controle de acesso. Apesar dos esforços, nenhum sistema é 100% imune — recomendamos
                    o uso de uma senha forte e exclusiva.
                </p>

                <h2 id="cookies">6. Cookies e sessão</h2>
                <p>
                    Usamos apenas os cookies necessários para manter você conectado e lembrar preferências (como o modo de
                    visualização "pessoal" ou "compartilhado"). Não usamos cookies de rastreamento publicitário de terceiros.
                </p>

                <h2 id="direitos">7. Seus direitos (LGPD)</h2>
                <p>Você pode, a qualquer momento:</p>
                <ul class="list-disc pl-5">
                    <li>Acessar e corrigir seus dados de cadastro e lançamentos diretamente no app.</li>
                    <li>Solicitar a confirmação da existência de tratamento dos seus dados.</li>
                    <li>Solicitar a exclusão da sua conta e dos dados associados.</li>
                    <li>Revogar consentimentos e obter informações sobre o tratamento.</li>
                </ul>
                <p>Para exercer esses direitos, entre em contato pelo e-mail informado abaixo.</p>

                <h2 id="retencao">8. Retenção e exclusão</h2>
                <p>
                    Mantemos seus dados enquanto sua conta estiver ativa. Ao solicitar a exclusão, seus dados pessoais e financeiros
                    são removidos, ressalvadas obrigações legais de retenção. Note que dados de escopo compartilhado podem permanecer
                    visíveis ao outro membro da família, pois pertencem ao histórico financeiro de ambos.
                </p>

                <h2 id="alteracoes">9. Alterações nesta política</h2>
                <p>
                    Podemos atualizar esta Política periodicamente. Mudanças relevantes serão sinalizadas no app ou por e-mail. A data
                    de "última atualização" no topo indica a versão vigente.
                </p>

                <h2 id="contato">10. Contato</h2>
                <p>
                    Dúvidas sobre privacidade ou sobre o tratamento dos seus dados? Fale com a gente:
                    <a href="mailto:contato@studiostg.com.br">contato@studiostg.com.br</a>.
                </p>
            </article>
        </div>
    </main>

    {{-- ===================== FOOTER ===================== --}}
    <footer class="border-t border-gray-200/70 bg-white">
        <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-4 px-5 py-8 sm:flex-row sm:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary"><span class="text-[10px] font-black text-white">DF</span></div>
                <span class="font-bold tracking-tight"><span class="text-primary">Duo</span>Fund</span>
            </a>
            <p class="text-xs text-gray-400">© {{ date('Y') }} DuoFund · Gerenciando finanças juntos.</p>
            <div class="flex items-center gap-5 text-sm font-medium text-gray-500">
                <a href="{{ route('login') }}" class="transition hover:text-primary">Entrar</a>
                <a href="{{ route('register') }}" class="transition hover:text-primary">Criar conta</a>
            </div>
        </div>
    </footer>

    <script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
