<div x-cloak x-show="mobile_panel === 'profile'" x-transition id="nb_mobile_profile_menu"
    class="fixed bottom-16 left-0 right-0 z-[1095] max-h-[min(70vh,30rem)] overflow-y-auto border-b border-white/10 bg-cbar px-2 py-3 shadow-[0_-8px_20px_rgba(0,0,0,0.18)] md:hidden">
    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-white/60">[#text Profile#]</div>
    <p class="px-2 pb-3 text-xs text-white/60">
        [#text Logged in as#] <br />
        <span class="text-neutral-100">[#username#]</span>
    </p>
    <ul class="m-0 flex list-none flex-col gap-1 p-0">
        <li>
            <a class="flex min-h-12 items-center gap-3 rounded px-2 text-sm font-medium text-neutral-100 outline-none transition hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:outline-none active:bg-clight"
                href="[#base-url#]/nb-admin/profile">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center text-neutral-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-5 w-5">
                        <title>[#text Profile#]</title>
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </span>
                <span>[#text Profile#]</span>
            </a>
        </li>
        <li>
            <a class="flex min-h-12 items-center gap-3 rounded px-2 text-sm font-medium text-neutral-100 outline-none transition hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:outline-none active:bg-clight"
                href="[#base-url#]/logout">
                <span class="flex h-6 w-6 shrink-0 items-center justify-center text-neutral-100">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-5 w-5">
                        <title>[#text Logout#]</title>
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>
                </span>
                <span>[#text Logout#]</span>
            </a>
        </li>
    </ul>
</div>
