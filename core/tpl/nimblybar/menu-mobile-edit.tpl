<div x-cloak x-show="mobile_panel === 'edit'" x-transition id="nb_mobile_edit_menu"
    class="fixed bottom-16 left-0 right-0 z-[1095] hidden border-b border-white/10 bg-cbar px-2 py-3 shadow-[0_-8px_20px_rgba(0,0,0,0.18)] md:hidden">
    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-white/60">[#text Edit#]</div>
    <div class="flex flex-col gap-1">
        <button type="button" data-nb-edit-toggle
            class="flex min-h-11 w-full cursor-pointer items-center rounded text-sm leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
                    class="h-5 w-5">
                    <title>[#text Edit#]</title>
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
                </svg>
            </span>
            [#text Edit#]
        </button>
        [#if nb-skip-insert-media=(empty) tpl=media-btn#]
        <button data-nb-edit-save disabled type="button"
            class="flex min-h-11 w-full cursor-pointer items-center truncate rounded text-sm leading-none text-neutral-100 outline-none transition hover:disabled:bg-transparent disabled:text-white/50 duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 motion-reduce:transition-none">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                    stroke="currentColor" class="h-5 w-5">
                    <title>[#text Save#]</title>
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            [#text Save#]
        </button>
        [#feature-cond edit-.config tpl=menu-mobile-page-settings#]
    </div>
</div>
