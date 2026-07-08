<div x-cloak x-show="mobile_panel === 'app'" x-transition id="nb_mobile_app_menu"
    class="fixed bottom-16 left-0 right-0 z-[1095] max-h-[min(70vh,30rem)] overflow-y-auto border-b border-white/10 bg-cbar px-2 py-3 shadow-[0_-8px_20px_rgba(0,0,0,0.18)] md:hidden">
    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-white/60">[#text App#]</div>
    <ul class="m-0 flex list-none flex-col gap-1 p-0">
        <li>
            <a class="flex min-h-12 items-center gap-3 rounded px-2 text-sm font-medium text-neutral-100 outline-none transition hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:outline-none active:bg-clight"
                href="[#base-url#]/">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center text-neutral-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                        stroke="currentColor" class="h-5 w-5">
                        <title>[#text Site home#]</title>
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                </span>
                <span>[#text Site home#]</span>
            </a>
        </li>
        [#set nimblybar-mobile-app-menu=#]
        [#nimblybar-mobile-app-menu#]
    </ul>
</div>
