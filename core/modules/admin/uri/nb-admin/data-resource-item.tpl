<li class="flex w-full flex-none flex-col text-sm sm:w-auto sm:min-w-[180px]">
    <div class="flex min-h-7 items-center gap-2">
        <a href="[#base-url#]/nb-admin/[#item.key#]" class="font-medium text-neutral-800 hover:text-primary hover:underline">
            [#text [#item.name#]#] ([#data-count [#item.key#]#])
        </a>
        [#feature-cond features=create-[#item.key#] tpl=data-resource-add#]
    </div>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Disk space#]: [#fmt [#disk-space-resource [#item.key#]#] bytes#]
    </div>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Updated#]: [#fmt [#data-last-update [#item.key#]#] ago#] 
    </div>
</li>
