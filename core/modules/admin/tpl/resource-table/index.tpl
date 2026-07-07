[#init-resource-table#]
<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#resource-name [#resource-id#] plural#]</span>
    </nav>
    <div class="flex min-w-0 flex-wrap items-center gap-2 md:flex-nowrap">
        <h1 class="min-w-0 basis-full break-words text-2xl font-semibold text-neutral-800 md:basis-auto md:text-3xl"
            data-nb-edit-options='{"buttons":""}'>
            [#text [#resource-name [#resource-id#] plural#]#]
        </h1>
        <input type="search"
            class="order-last min-h-11 w-full min-w-0 basis-full rounded border border-neutral-300 bg-neutral-50 px-4 py-2 text-neutral-800 placeholder:text-neutral-500 focus:outline-2 focus:outline-cnormal md:order-none md:ml-auto md:mr-4 md:min-h-0 md:w-64 md:basis-auto md:py-1.5 lg:w-80"
            placeholder="[#text Search#]"
            x-data="{search_term: ''}"
            x-init="search_term=''"
            @input.debounce.150ms="$dispatch('search', $event.target.value)"
        />
        [#feature-cond create-[#resource-id#] tpl=btn_add#]
        [#feature-cond import-[#resource-id#] tpl=btn_import#]
        [#feature-cond features="export-[#resource-id#]" tpl=btn_export#]
    </div>
    <h3 class="px-3 pb-2 pt-1 text-sm font-medium text-neutral-700 sm:px-4 md:text-base">[#count data.records#] [#text records#]</h3>

    [#resource-table#]


</section>
