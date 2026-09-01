[#init-resource-table#]
<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary"
    x-data="data_table()"
    @search.window="search($event.detail || '')">

    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#resource-name [#resource-id#] plural#]</span>
    </nav>
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
        <h1 class="min-w-0 break-words text-2xl font-semibold text-neutral-800 md:text-3xl"
            data-nb-edit-options='{"buttons":""}'>
            [#text [#resource-name [#resource-id#] plural#]#]
        </h1>
        <span class="text-sm text-neutral-500" data-nb-record-count>
            <span x-text="filtered_count()"></span>
            [#text of#]
            <span x-text="record_count()"></span>
            [#text records#]
        </span>
    </div>

    <div class="mt-4 rounded-box border border-base-300 bg-base-100 p-2 sm:p-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <label class="input w-full sm:w-56 lg:w-64">
                <svg class="size-4 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search"
                    placeholder="[#text Search#]"
                    x-data="{search_term: ''}"
                    x-init="search_term=''"
                    @input.debounce.150ms="$dispatch('search', $event.target.value)"
                />
            </label>
            <template x-for="(filter, field_id) in _filters" :key="field_id">
                <label class="select w-full sm:w-auto sm:min-w-40" x-cloak>
                    <span class="label" x-text="filter.name"></span>
                    <select
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
            <span class="flex shrink-0 items-center gap-1 sm:ms-auto">
                [#feature-cond create-[#resource-id#] tpl=btn_add#]
                [#feature-cond import-[#resource-id#] tpl=btn_import#]
                [#feature-cond features="export-[#resource-id#]" tpl=btn_export#]
            </span>
        </div>
    </div>

    [#resource-table#]


</section>
