<li class="relative mt-8">
    <a class="flex h-[30px] rounded truncate 
        cursor-pointer items-center
        text-[0.875rem] text-neutral-100 outline-none transition duration-300 ease-linear
         hover:bg-clight hover:text-neutral-50 hover:outline-none focus:bg-clight
         focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50
         active:outline-none data-[te-sidenav-state-active]:text-neutral-50
          data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none" data-te-sidenav-link-ref>

        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
            class="w-[22px] h-[22px] mx-[4px]">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
        </svg>

        <span class="group-[&[data-te-sidenav-slim-collapsed='true']]:data-[te-sidenav-slim='false']:hidden ml-[12px]"
            data-te-sidenav-slim="false">[#text Resources#]</span>
        <span
            class="absolute right-0 ml-auto mr-[0.5rem] transition-transform duration-300 ease-linear motion-reduce:transition-none [&>svg]:text-neutral-100 "
            data-te-sidenav-rotate-icon-ref data-te-sidenav-slim="false">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd" />
            </svg>
        </span>
    </a>
    <ul class="!visible relative mx-0 my-2 hidden list-none p-0 data-[te-collapse-show]:block "
        data-te-sidenav-collapse-ref>
        [#is-url nb-admin/users#]
        <li class='relative [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
            <a class="flex h-6 cursor-pointer items-center truncate rounded-[5px] py-4 pl-[42px] pr-2 text-[0.8rem] text-neutral-100 
                outline-none transition 
                duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50 
                focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none 
                data-[te-sidenav-state-active]:text-neutral-50 data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none"
                data-te-sidenav-link-ref="[#base-url#]/nb-admin/users" href="[#base-url#]/nb-admin/users">
                [#text Users#]
            </a>
        </li>
        [#is-url nb-admin/media#]
        <li class='relative [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
            <a class="flex h-6 cursor-pointer items-center truncate rounded-[5px] py-4 pl-[42px] pr-2 text-[0.8rem] text-neutral-100 
                outline-none transition 
                duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50 
                focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none 
                data-[te-sidenav-state-active]:text-neutral-50 data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none"
                data-te-sidenav-link-ref="[#base-url#]/nb-admin/users" href="[#base-url#]/nb-admin/media">
                [#text Media Library#]
            </a>
        </li>
        [#feature-cond manage-content tpl=menu-site-config-item#]
        [#feature-cond list_shortcodes tpl=menu-shortcodes-item#]
        [#get-user-resources#]
        [#repeat data.user-resources tpl=menu-resource-item#]
    </ul>
</li>