[#is-url nb-admin/[#item.key#]#]
<li class='relative flex items-center rounded-[5px] [#if is-url=(not-empty) echo="bg-clight/20 font-bold"#]'>
    <a class="mr-4 flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-[5px] py-1.5 pl-10 pr-2 text-[0.8rem] leading-tight text-neutral-100 md:min-h-8
                        outline-none transition
                        duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50
        focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none motion-reduce:transition-none"
        href="[#base-url#]/nb-admin/[#item.key#]" title="[#text [#item.name#]#]">
        <span class="min-w-0 flex-1 break-words">[#text [#item.name#]#]</span>
        <span class="shrink-0 rounded-full bg-white/10 px-1.5 py-0.5 text-[0.7rem] tabular-nums">([#data-count [#item.key#]#])</span>
    </a>
    [#feature-cond features=create-[#item.key#] tpl=menu-resource-add#]
</li>
