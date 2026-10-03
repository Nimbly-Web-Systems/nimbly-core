<div id="nb-media-toolbar" class="flex flex-wrap items-center gap-2" @pointerenter.once="load_usage()"
    @focusin.once="load_usage()">
    <label class="input input-bordered input-sm flex w-full items-center gap-2 bg-white sm:w-64">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="h-4 w-4 shrink-0 text-neutral-500">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input type="text" class="min-w-0 grow" x-model="search" @input.debounce.150ms="apply_filters()"
            @keydown.escape="if (search) { $event.stopPropagation(); search = ''; apply_filters() }" placeholder="[#text Search#]"
            aria-label="[#text Search#]" />
        <button type="button" x-show="search" x-cloak class="text-neutral-500 hover:text-neutral-800"
            @click="search = ''; apply_filters()" aria-label="[#text Clear search#]">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </label>

    <select class="select select-bordered select-sm w-auto bg-white" x-model="type_filter" @change="apply_filters()"
        x-show="type_options().length > 1" aria-label="[#text Type#]">
        <option value="">[#text All types#]</option>
        <template x-if="type_available('img')"><option value="img">[#text Images#]</option></template>
        <template x-if="type_available('vid')"><option value="vid">[#text Video#]</option></template>
        <template x-if="type_available('audio')"><option value="audio">[#text Audio#]</option></template>
        <template x-if="type_available('doc')"><option value="doc">[#text Documents#]</option></template>
    </select>

    <select class="select select-bordered select-sm w-auto max-w-[14rem] bg-white" x-model="usage_filter"
        @change="set_usage_filter()" aria-label="[#text Location#]">
        <option value="">[#text All locations#]</option>
        <option value="(unused)">[#text Not in use#]</option>
        <template x-for="group in usage_groups" :key="group.key">
            <option :value="group.key" x-text="`${group.name} (${group.count})`"></option>
        </template>
    </select>
    <span class="loading loading-spinner loading-xs text-neutral-500" x-show="usage_loading" x-cloak></span>

    <select class="select select-bordered select-sm w-auto bg-white" x-model="sort" @change="apply_filters()"
        aria-label="[#text Sort#]">
        <option value="newest">[#text Newest#]</option>
        <option value="oldest">[#text Oldest#]</option>
        <option value="name">[#text Name#]</option>
    </select>

    <span class="text-sm tabular-nums text-neutral-600">
        <span x-text="files.length"></span>
        [#text files#]
    </span>
</div>
