[#init-resource-table#]
<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary"
    x-data="data_table()"
    @search.window="search($event.detail || '')">

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
    <div x-show="Object.keys(_filters).length" x-cloak
        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <template x-for="(filter, field_id) in _filters" :key="field_id">
            <label class="block min-w-0 text-sm font-medium text-neutral-700">
                <span x-text="filter.name"></span>
                <select class="mt-1 min-h-11 w-full rounded border border-neutral-300 bg-neutral-50 px-3 py-2 text-neutral-800 focus:outline-2 focus:outline-cnormal md:min-h-0 md:py-1.5"
                    :name="`filter[${field_id}]`"
                    x-model="filter_values[field_id]"
                    x-effect="$nextTick(() => { $el.value = filter_values[field_id] })"
                    @change="change_filter(field_id, $event.target.value)">
                    <option value="">[#text All#]</option>
                    <template x-for="(label, value) in filter.options" :key="value">
                        <option :value="String(value)" x-text="label"></option>
                    </template>
                </select>
            </label>
        </template>
    </div>
    <h3 class="px-3 pb-2 pt-3 text-sm font-medium text-neutral-700 sm:px-4 md:text-base">
        <span x-text="filtered_count()"></span>
        <span>[#text of#]</span>
        <span x-text="record_count()"></span>
        <span>[#text records#]</span>
    </h3>

    [#resource-table#]


</section>
