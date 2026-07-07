<div class="mb-6 flex flex-col items-stretch gap-2 sm:flex-row sm:flex-wrap sm:items-center" x-data="resource_switcher_live('[#_rs.resource#]', '[#_rs.title_field#]')">
    <div class="relative w-full sm:w-auto">
        <input type="search" x-model="query" @input.debounce.200ms="search()"
            placeholder="[#text Jump to a record...#]"
            class="min-h-11 w-full rounded border border-neutral-300 bg-white px-3 py-2 text-sm focus:outline-2 focus:outline-cnormal sm:w-64 sm:py-1.5">
        <ul x-show="results.length" x-cloak
            class="absolute z-20 mt-1 w-full overflow-hidden rounded border border-neutral-200 bg-white shadow-md sm:w-64">
            <template x-for="r in results" :key="r.uuid">
                <li>
                    <a :href="r.url" class="block truncate px-3 py-2 text-sm hover:bg-neutral-100" x-text="r.title"></a>
                </li>
            </template>
        </ul>
    </div>
    <a href="[#base-url#][#_rs.add_url#]"
        class="inline-flex min-h-11 items-center justify-center gap-1 rounded-md border border-dashed border-neutral-400 px-3 py-2 text-sm font-medium text-neutral-600 hover:border-cnormal hover:text-cnormal sm:min-h-0 sm:rounded-full sm:py-1.5">
        + [#text Add#]
    </a>
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/resource-switcher/resource-switcher-live.js#]
    </script>
</div>
