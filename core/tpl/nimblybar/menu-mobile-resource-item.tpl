[#is-url nb-admin/[#item.key#]#]
<li class='relative flex min-h-12 items-center rounded [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
    <a class="flex min-h-12 flex-1 cursor-pointer items-center truncate rounded-l pl-2 pr-3 text-sm leading-none text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 motion-reduce:transition-none"
        href="[#base-url#]/nb-admin/[#item.key#]">
        [#text [#item.name#]#]
    </a>
    [#feature-cond create-[#item.key#] tpl=menu-mobile-resource-add#]
</li>
