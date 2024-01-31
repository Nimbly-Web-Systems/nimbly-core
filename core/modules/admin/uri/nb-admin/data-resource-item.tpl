<li class="flex flex-col text-sm flex-1 min-w-[120px]">
    <a href=[#base-url#]/nb-admin/[#item.key#] class="hover:underline">[#item.name#] ([#data-count [#item.key#]#])</a>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Disk space#]: [#fmt [#disk-space-resource [#item.key#]#] bytes#]
    </div>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Updated#]: [#fmt [#data-last-update [#item.key#]#] ago#] 
    </div>
</li>