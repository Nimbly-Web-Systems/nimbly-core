[#is-url nb-admin/[#item.key#]#]
<li class='relative flex items-center rounded-[5px] [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
    <a class="mr-4 flex min-h-11 min-w-0 flex-1 cursor-pointer items-center truncate rounded-[5px] pl-10 pr-2 text-[0.8rem] leading-none text-neutral-100 md:min-h-8
                        outline-none transition
                        duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50
                        focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none motion-reduce:transition-none"
        href="[#base-url#]/nb-admin/[#item.key#]">
        [#text [#item.name#]#] ([#item.count#])
    </a>
    [#feature-cond features=create-[#item.key#] tpl=menu-resource-add#]
</li>
