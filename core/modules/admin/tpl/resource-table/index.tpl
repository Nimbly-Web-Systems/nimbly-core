[#init-resource-table#]
<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">

    <div class="flex flex-row items-center">
        <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800"
            data-nb-edit-options='{"buttons":""}'>
            [#text [#resource-name#]#]
        </h1>
        <input type="search" class="ml-auto mr-6 py-1.5 px-4 focus:outline-2 focus:outline-cnormal"
            placeholder="[#text Search#]" 
            x-data="{search_term: ''}"
            x-init="search_term=''"
            @input.debounce.150ms="$dispatch('search', $event.target.value)"
        />
        [#feature-cond add-[#resource-name#] tpl=btn_add#]
        [#feature-cond import-[#resource-name#] tpl=btn_import#]
    </div>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#count data.records#] [#text records#]</h3>

    [#resource-table#]

    
</section>
<template>
    <div class="bg-yellow-200">plz build this style</div>
</template>