<div class="relative mb-8 w-40" x-data="{ open: false, query: '' }">
    <button type="button" @click="open = !open"
        class="btn btn-outline btn-sm w-full justify-between uppercase">
        <span x-text="lang"></span>
        <span aria-hidden="true">&#9662;</span>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false"
        class="absolute z-10 mt-1 w-full rounded-md border border-neutral-300 bg-neutral-50 shadow-lg">
        <input type="text" x-model="query" x-ref="query" @focus="open = true"
            placeholder="[#text Search#]"
            class="input input-bordered input-sm w-full rounded-b-none" />
        <ul class="max-h-56 overflow-y-auto py-1">
            [#repeat languages tpl=item#]
        </ul>
    </div>
</div>
