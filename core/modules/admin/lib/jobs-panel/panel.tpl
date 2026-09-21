<div x-data="jobs_panel()">
    <script>
        var _jobs_panel_records = [#_jp.records_json#];
    </script>
    <p class="mb-3 text-sm text-neutral-500">[#_jp.counts#]</p>
    <div class="mb-4 rounded-box border border-base-300 bg-base-100 p-2 sm:p-3" x-show="records.length > 0">
        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            <label class="input w-full sm:w-56 lg:w-64">
                <svg class="size-4 opacity-50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input type="search" placeholder="[#text Search#]" x-model.debounce.150ms="search_term" />
            </label>
            <label class="select w-full sm:w-auto sm:min-w-40">
                <span class="label">[#text Status#]</span>
                <select x-model="status_filter">
                    <option value="">[#text All#]</option>
                    <template x-for="status in statuses()" :key="status">
                        <option :value="status" x-text="status"></option>
                    </template>
                </select>
            </label>
            <label class="select w-full sm:w-auto sm:min-w-40">
                <span class="label">[#text Type#]</span>
                <select x-model="type_filter">
                    <option value="">[#text All#]</option>
                    <template x-for="type in types()" :key="type">
                        <option :value="type" x-text="type"></option>
                    </template>
                </select>
            </label>
            <label class="select w-full sm:w-auto sm:min-w-32">
                <span class="label">[#text Attempts#]</span>
                <select x-model="attempts_filter">
                    <option value="">[#text All#]</option>
                    <template x-for="count in attempt_counts()" :key="count">
                        <option :value="String(count)" x-text="count"></option>
                    </template>
                </select>
            </label>
            <span class="text-sm text-neutral-500 sm:ml-auto">
                <span x-text="filtered_count()"></span> [#text of#] <span x-text="records.length"></span> [#text jobs#]
            </span>
        </div>
    </div>
    [#_jp.body#]
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/jobs-panel/jobs-panel.js#]
    </script>
</div>
