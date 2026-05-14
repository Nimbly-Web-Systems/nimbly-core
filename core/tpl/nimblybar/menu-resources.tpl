<li class="relative">
    <button type="button" class="flex h-8 w-full cursor-pointer items-center rounded text-[0.875rem] leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight hover:text-neutral-50 focus:bg-clight focus:text-neutral-50 focus:outline-none"
        @click="resources_open = !resources_open" :aria-expanded="resources_open.toString()">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
                class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
            </svg>
        </span>
        <span class="ml-2">[#text Resources#]</span>
        <span class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center transition-transform duration-200" :class="resources_open ? 'rotate-180' : ''">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0">
                <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd" />
            </svg>
        </span>
    </button>
    <ul x-cloak x-show="resources_open" class="relative mx-0 my-2 list-none p-0">
        [#is-url nb-admin/users#]
        <li class='relative [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
            <a class="flex h-8 cursor-pointer items-center truncate rounded-[5px] pl-10 pr-2 text-[0.8rem] leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-slate-50 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none motion-reduce:transition-none"
                href="[#base-url#]/nb-admin/users">
                [#text Users#]
            </a>
        </li>
        [#is-url nb-admin/media#]
        <li class='relative [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
            <a class="flex h-8 cursor-pointer items-center truncate rounded-[5px] pl-10 pr-2 text-[0.8rem] leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-slate-50 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none motion-reduce:transition-none"
                href="[#base-url#]/nb-admin/media">
                [#text Media Library#]
            </a>
        </li>
        [#feature-cond features="manage-system,manage-.config,edit-.config,(any)_.config" tpl=menu-site-config-item#]
        [#feature-cond list_shortcodes tpl=menu-shortcodes-item#]
        [#get-user-resources#]
        [#repeat data.user-resources tpl=menu-resource-item#]
    </ul>
</li>
