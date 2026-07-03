<div class="mb-6 flex flex-wrap items-center gap-2" x-data="resource_switcher_live('[#_rs.resource#]', '[#_rs.title_field#]')">
    <div class="relative">
        <input type="search" x-model="query" @input.debounce.200ms="search()"
            placeholder="[#text Jump to a record...#]"
            class="w-64 rounded border border-neutral-300 bg-white px-3 py-1.5 text-sm focus:outline-2 focus:outline-cnormal">
        <ul x-show="results.length" x-cloak
            class="absolute z-20 mt-1 w-64 overflow-hidden rounded border border-neutral-200 bg-white shadow-md">
            <template x-for="r in results" :key="r.uuid">
                <li>
                    <a :href="r.url" class="block truncate px-3 py-2 text-sm hover:bg-neutral-100" x-text="r.title"></a>
                </li>
            </template>
        </ul>
    </div>
    <a href="[#base-url#][#_rs.add_url#]"
        class="inline-flex items-center gap-1 rounded-full border border-dashed border-neutral-400 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:border-cnormal hover:text-cnormal">
        + [#text Add#]
    </a>
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/resource-switcher/resource-switcher-live.js#]
    </script>
</div>
