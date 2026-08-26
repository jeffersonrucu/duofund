{{-- Guia "Adicionar à Tela de Início" (PWA / A2HS) --}}
{{-- Uso: @include('partials.install-pwa', ['offset' => 'bottom-24']) --}}
@php $offset = $offset ?? 'bottom-4'; @endphp

<div x-data="installPwa()" x-cloak class="lg:hidden">
    {{-- Banner --}}
    <div x-show="show"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 translate-y-4"
         class="fixed {{ $offset }} inset-x-3 z-40" style="margin-bottom: env(safe-area-inset-bottom, 0px)">
        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white p-3 shadow-xl shadow-gray-300/40">
            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-primary shadow-md shadow-primary/25">
                <span class="text-xs font-black tracking-tighter leading-none text-white">DF</span>
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold leading-tight text-gray-900">Instale o DuoFund</p>
                <p class="text-[11px] leading-tight text-gray-500">Adicione à tela inicial e abra como um app.</p>
            </div>
            <button @click="trigger()"
                    class="flex-shrink-0 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-white transition hover:bg-secondary">
                Instalar
            </button>
            <button @click="dismiss()" class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600" aria-label="Dispensar">
                <x-lucide-x class="h-4 w-4" />
            </button>
        </div>
    </div>

    {{-- Modal com passo a passo --}}
    <div x-show="modal" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center sm:items-center"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="modal = false"></div>

        <div class="relative w-full max-w-sm rounded-t-3xl bg-white p-5 shadow-2xl sm:rounded-3xl"
             x-transition:enter="transition ease-out duration-250" x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-base font-bold text-gray-900">Adicionar à tela inicial</h3>
                <button @click="modal = false" class="p-1 text-gray-400 hover:text-gray-600"><x-lucide-x class="h-5 w-5" /></button>
            </div>

            {{-- iOS --}}
            <div x-show="ios" class="space-y-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">1</span>
                    <p class="text-sm text-gray-600">Toque no menu
                        <span class="mx-0.5 inline-flex h-5 w-5 -translate-y-0.5 items-center justify-center rounded bg-gray-100 align-middle"><x-lucide-more-horizontal class="h-3 w-3 text-primary" /></span>
                        na barra do navegador.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">2</span>
                    <p class="text-sm text-gray-600">Toque em <strong>Compartilhar</strong>
                        <span class="mx-0.5 inline-flex h-5 w-5 -translate-y-0.5 items-center justify-center rounded bg-gray-100 align-middle"><x-lucide-share class="h-3 w-3 text-primary" /></span>.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">3</span>
                    <p class="text-sm text-gray-600">Toque em <strong>Mais</strong>
                        <span class="mx-0.5 inline-flex h-5 w-5 -translate-y-0.5 items-center justify-center rounded bg-gray-100 align-middle"><x-lucide-more-horizontal class="h-3 w-3 text-primary" /></span>
                        e escolha <strong>“Adicionar à Tela de Início”</strong>.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">4</span>
                    <p class="text-sm text-gray-600">Confirme em <strong>“Adicionar”</strong>. Pronto — o ícone aparece na tela inicial.</p>
                </div>
            </div>

            {{-- Android --}}
            <div x-show="!ios" class="space-y-3">
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">1</span>
                    <p class="text-sm text-gray-600">Toque no menu
                        <span class="mx-0.5 inline-flex h-5 w-5 -translate-y-0.5 items-center justify-center rounded bg-gray-100 align-middle"><x-lucide-more-vertical class="h-3 w-3 text-primary" /></span>
                        no canto do Chrome.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">2</span>
                    <p class="text-sm text-gray-600">Escolha <strong>“Adicionar à tela inicial”</strong> ou <strong>“Instalar app”</strong>.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">3</span>
                    <p class="text-sm text-gray-600">Confirme. O DuoFund vai abrir como um app, em tela cheia.</p>
                </div>
            </div>

            <button @click="modal = false" class="mt-5 w-full rounded-xl bg-gray-100 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-200">
                Entendi
            </button>
        </div>
    </div>
</div>

<script>
    // localStorage seguro (Safari em modo privado lança exceção no setItem)
    function lsGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }

    function installPwa() {
        return {
            show: false, modal: false, ios: false, deferred: null,
            init() {
                const ua = navigator.userAgent || '';
                const isMobile = /android|iphone|ipad|ipod/i.test(ua);
                // iPhone/iPad (inclui iPad que se identifica como Mac com toque)
                this.ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
                const standalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
                const installed = lsGet('a2hs_installed') === '1';
                // Mostra no máximo 1x a cada 24h enquanto não instalado
                const ONE_DAY = 24 * 60 * 60 * 1000;
                const last = parseInt(lsGet('a2hs_last_v2') || '0', 10);
                const shownRecently = last && (Date.now() - last) < ONE_DAY;
                // Captura o prompt nativo do Android/Chrome, se disponível
                window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.deferred = e; });
                // Já instalado: nunca mais mostrar
                window.addEventListener('appinstalled', () => { lsSet('a2hs_installed', '1'); this.show = false; });

                if ((isMobile || this.ios) && !standalone && !installed && !shownRecently) {
                    lsSet('a2hs_last_v2', String(Date.now())); // marca a exibição de hoje
                    setTimeout(() => { this.show = true; }, 1200);
                }
            },
            async trigger() {
                if (this.deferred) {           // Android: instalação nativa
                    this.deferred.prompt();
                    await this.deferred.userChoice;
                    this.deferred = null;
                    this.dismiss();
                } else {                        // iOS ou Android sem prompt: passo a passo
                    this.modal = true;
                }
            },
            dismiss() {
                this.show = false;
                // timestamp já foi gravado ao exibir; não reaparece por 24h
                lsSet('a2hs_last_v2', String(Date.now()));
            }
        }
    }
</script>
