<li class="relative hidden" id="nb_edit_menu">
    <button type="button" class="flex h-8 w-full cursor-pointer items-center rounded text-[0.875rem] leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight hover:text-neutral-50 focus:bg-clight focus:text-neutral-50 focus:outline-none"
        id="nb_edit_toggler" :aria-expanded="edit_open.toString()">

        <span class="flex h-8 w-8 shrink-0 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
                class="h-5 w-5">
                <title>[#text Edit#]</title>
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
            </svg>
        </span>

        <span class="ml-2">[#text Edit#]</span>
        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center transition-transform duration-200" :class="edit_open ? 'rotate-180' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0">
                <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd" />
            </svg>
        </span>
    </button>
    <ul x-cloak x-show="edit_open" class="relative mx-0 my-2 list-none p-0">
        <li class='relative'>
            [#if nb-skip-insert-media=(empty) tpl=media-btn#]

            [#nop #feature-cond edit-inline-content tpl=html-btn#]

            <button id="nb_edit_save" disabled type="button"
                class="flex h-8 w-full cursor-pointer items-center truncate rounded-[5px] pr-2 text-[0.8rem] leading-none text-neutral-100 outline-none transition hover:disabled:bg-transparent disabled:text-white/50 duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-slate-50 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none motion-reduce:transition-none">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                        stroke="currentColor" class="h-5 w-5">
                        <title>[#text Save#]</title>
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                [#text Save#]
            </button>
        </li>
    </ul>
</li>

<template id="nb_edit_img_btn">
    <button class="absolute right-0 top-0 cursor-pointer bg-clight/50 p-2 text-cdarkest hover:bg-clight/80"
        data-nb-open-media-modal type="button" title="[#text Select image#]">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="h-6 w-6">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12A1.5 1.5 0 003.75 19.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
    </button>
</template>
