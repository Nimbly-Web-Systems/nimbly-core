[#get-resources#]
<div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
    <div class="flex items-start justify-between">
        <div class="text-lg font-primary font-medium truncate text-neutral-900">
            [#text Data#]
        </div>
    </div>
    <div class=" text-rose-600 font-bold mt-4 w-full">
        <ul class="relative flex flex-row flex-wrap justify-evenly gap-x-4 gap-y-2 overflow-hidden max-h-[180px]"
            data-te-perfect-scrollbar-init>
            [#repeat data.user-resources tpl=data-resource-item#]
        </ul>
    </div>
</div>