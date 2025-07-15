<div class="w-full px-4 py-2 rounded-md shadow-md bg-neutral-50 mt-4" 
    x-data="data_table()"
    @search.window="search($event.detail || '')">
    <table class="min-w-full">
        <thead>
            <tr>
                <template x-for="(field, field_id) in _fields" :key="field_id">
                    <th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left" x-ref="field_id">
                        <template x-if="field.sortable !== undefined && !field.sortable">
                            <span x-text="field.name"></span>
                        </template>
                        <template x-if="field.sortable === undefined || field.sortable">
                            <div class="flex items-center gap-2 group cursor-pointer"
                                @click="toggle_sort(field_id)">
                                <span x-text="field.name"></span>
                                <button x-show="is_sorted_asc(field_id)" class="block">
                                    [#svg-chevron-up-4#]
                                </button>
                                <button x-show="is_sorted_desc(field_id)" class="block">
                                    [#svg-chevron-down-4#]
                                </button>
                                <button x-show="!is_sorted_asc(field_id) && !is_sorted_desc(field_id)" x-cloak
                                    class="group-hover:visible invisible">
                                    [#svg-chevron-down-4#]
                                </button>
                            </div>
                        </template>
                    </th>
                </template>
                <th scope="col" class="font-bold border-b border-neutral-200 py-3 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            <template x-if="total_count() < 1">
                <tr>
                    <td class="text-neutral-600 py-3 border-b border-neutral-200"
                        :colspan="Object.keys(_fields).length">
                        <p x-text="search_regex? '[#text No search results#]': '[#text No records yet#]'" ></p>
                    </td>
                    <td class="text-neutral-600 py-3 border-b border-neutral-200">
                        <template x-if="Object.keys(_records).length < 1">
                            [#feature-cond add-[#resource-name#] tpl=action_add#]
                        </template>
                    </td>
                </tr>
            </template>
            
            <template x-for="(record, record_id) in page_records" :key="record_id">
                <tr :x-ref="record_id">
                    <template x-for="(field, field_id) in _fields">
                        <td class="text-neutral-600 py-3 border-b border-neutral-200" 
                        x-html="highlight(record[field_id])">
                        </td>
                    </template>
                    <td class="text-neutral-600 py-3 border-b border-neutral-200">
                        <div class="flex items-center#]">
                            [#actions#]
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
    <div class="flex flex-row items-center py-4">
        <div></div>
        <div class="ml-auto text-sm text-neutral-700">
            [#text Rows per page:#]
            <select x-model="page_size"
                class="px-2 pt-1 pb-2 ml-2 border border-neutral-200 focus:border-primary rounded"
                @change="store_page_size()">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="9999999">[#text All#]</option>
            </select>
        </div>
        <div x-text="Math.min(offset + 1, total_count()) + ' - ' + Math.min(total_count(), offset + page_size) + ' of ' + Object.keys(filtered_records).length"
            class="ml-6 text-sm text-neutral-700">
        </div>
        <div class="mx-4">
            <button
                class="pt-1.5 text-neutral-700 disabled:text-neutral-300 disabled:pointer-events-none hover:bg-neutral-100"
                @click="prev()" :disabled="page < 2" x-ref="btn_prev_page">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4">
                    <path fill-rule="evenodd"
                        d="M7.72 12.53a.75.75 0 0 1 0-1.06l7.5-7.5a.75.75 0 1 1 1.06 1.06L9.31 12l6.97 6.97a.75.75 0 1 1-1.06 1.06l-7.5-7.5Z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        <div>
            <button
                class="pt-1.5 text-neutral-700 disabled:text-neutral-300 disabled:pointer-events-none hover:bg-neutral-100"
                :disabled="(offset + page_size) >= Object.keys(_records).length" x-ref="btn_next_page"
                @click="next()">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4">
                    <path fill-rule="evenodd"
                        d="M16.28 11.47a.75.75 0 0 1 0 1.06l-7.5 7.5a.75.75 0 0 1-1.06-1.06L14.69 12 7.72 5.03a.75.75 0 0 1 1.06-1.06l7.5 7.5Z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
</div>