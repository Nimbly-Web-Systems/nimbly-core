<li class="flex w-full flex-none flex-col text-sm sm:w-auto sm:min-w-[210px]">
    <a href="[#base-url#]/nb-admin/[#item.key#]" class="mb-0.5 font-medium text-neutral-800 hover:text-primary hover:underline">
        [#text [#item.name#]#] ([#data-count [#item.key#]#])
    </a>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Disk space#]: [#fmt [#disk-space-resource [#item.key#]#] bytes#]
    </div>
    <div class="text-xs text-neutral-500 font-normal">
        [#text Updated#]: [#fmt [#data-last-update [#item.key#]#] ago#] 
    </div>
    [#feature-cond features=create-[#item.key#] tpl=data-resource-add#]
</li>
