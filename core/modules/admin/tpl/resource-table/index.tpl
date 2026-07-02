[#init-resource-table#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">

    <div class="flex flex-wrap md:flex-nowrap items-center gap-2">
        <h1 class="w-full md:w-auto text-2xl md:text-3xl font-semibold text-neutral-800"
            data-nb-edit-options='{"buttons":""}'>
            [#text [#resource-name [#resource-id#] plural#]#]
        </h1>
        <input type="search"
            class="order-last w-full md:order-none md:w-auto md:ml-auto md:mr-6 py-1.5 px-4 rounded border border-neutral-300 bg-neutral-50 text-neutral-800 placeholder:text-neutral-500 focus:outline-2 focus:outline-cnormal"
            placeholder="[#text Search#]"
            x-data="{search_term: ''}"
            x-init="search_term=''"
            @input.debounce.150ms="$dispatch('search', $event.target.value)"
        />
        [#feature-cond create-[#resource-id#] tpl=btn_add#]
        [#feature-cond import-[#resource-id#] tpl=btn_import#]
        [#feature-cond features="export-[#resource-id#]" tpl=btn_export#]
    </div>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count data.records#] [#text records#]</h3>

    [#resource-table#]


</section>
<template>
    <div class="bg-yellow-200">plz build this style</div>
</template>
