[#is-url nb-admin/[#item.key#]#]
<li class='relative [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
    <a class="flex h-6 cursor-pointer items-center truncate rounded-[5px] py-4 pl-[42px] pr-2 text-[0.8rem] text-neutral-100 
                        outline-none transition 
                        duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50 
                        focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none 
                        data-[te-sidenav-state-active]:text-neutral-50 data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none"
        data-te-sidenav-link-ref="[#base-url#]/nb-admin/users" href="[#base-url#]/nb-admin/[#item.key#]">
        [#text [#item.name#]#]
    </a>
</li>