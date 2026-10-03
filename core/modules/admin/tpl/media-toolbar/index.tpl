<div id="nb-media-toolbar" class="flex flex-wrap items-center gap-2">
    <label class="input input-bordered input-sm flex w-full items-center gap-2 bg-white sm:w-64">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="h-4 w-4 shrink-0 text-neutral-500">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
        </svg>
        <input type="text" class="min-w-0 grow" x-model="search" @input.debounce.150ms="apply_filters()"
            @keydown.escape.stop="search = ''; apply_filters()" placeholder="[#text Search#]"
            aria-label="[#text Search#]" />
        <button type="button" x-show="search" x-cloak class="text-neutral-500 hover:text-neutral-800"
            @click="search = ''; apply_filters()" aria-label="[#text Clear search#]">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </label>

    <div class="join" x-show="type_options().length > 1">
        <button type="button" class="btn btn-sm join-item" :class="type_filter === '' ? 'btn-active' : ''"
            @click="set_type_filter('')">[#text All#]</button>
        <template x-if="type_available('img')">
            <button type="button" class="btn btn-sm join-item" :class="type_filter === 'img' ? 'btn-active' : ''"
                @click="set_type_filter('img')">[#text Images#]</button>
        </template>
        <template x-if="type_available('vid')">
            <button type="button" class="btn btn-sm join-item" :class="type_filter === 'vid' ? 'btn-active' : ''"
                @click="set_type_filter('vid')">[#text Video#]</button>
        </template>
        <template x-if="type_available('audio')">
            <button type="button" class="btn btn-sm join-item" :class="type_filter === 'audio' ? 'btn-active' : ''"
                @click="set_type_filter('audio')">[#text Audio#]</button>
        </template>
        <template x-if="type_available('doc')">
            <button type="button" class="btn btn-sm join-item" :class="type_filter === 'doc' ? 'btn-active' : ''"
                @click="set_type_filter('doc')">[#text Documents#]</button>
        </template>
    </div>

    <button type="button" class="btn btn-sm" :class="unused_only ? 'btn-active' : ''" :disabled="unused_loading"
        :aria-pressed="unused_only" @click="toggle_unused()">
        <span class="loading loading-spinner loading-xs" x-show="unused_loading" x-cloak></span>
        [#text Not in use#]
    </button>

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
