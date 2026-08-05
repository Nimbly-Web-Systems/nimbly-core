[#is-url nb-admin/[#item.key#]#]
<li class='relative flex min-h-12 items-center rounded [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
    <a class="flex min-h-12 min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-l py-2 pl-2 pr-3 text-sm leading-tight text-neutral-100 outline-none transition duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 focus:bg-clight/40 focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 motion-reduce:transition-none"
        href="[#base-url#]/nb-admin/[#item.key#]" title="[#text [#item.name#]#]">
        <span class="min-w-0 flex-1 break-words">[#text [#item.name#]#]</span>
        <span class="shrink-0 rounded-full bg-white/10 px-2 py-1 text-xs tabular-nums">([#data-count [#item.key#]#])</span>
    </a>
    [#feature-cond features=create-[#item.key#] tpl=menu-mobile-resource-add tpl_else=menu-mobile-resource-add-placeholder#]
</li>
