<div [#_f.x_init#]
    class="overflow-hidden rounded-lg border border-base-300 bg-white focus-within:outline focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-neutral-400">
    <div x-data="{ query: '', option_count: 0 }"
        x-init="$nextTick(() => option_count = $refs.options.querySelectorAll('label').length)">
        <div x-cloak x-show="option_count > 10" class="border-b border-base-200 p-2 pt-4">
            <input type="search" x-model="query" class="input input-bordered input-sm w-full"
                placeholder="[#text Search options#]" aria-label="[#text Search options#]">
        </div>
        <div x-ref="options" class="max-h-72 divide-y divide-base-200 overflow-y-auto">
            [#if _f.resource=(not-empty) tpl=resource-options-check#]
            [#if _f.options=(not-empty) tpl=inline-options-check#]
        </div>
    </div>
</div>
