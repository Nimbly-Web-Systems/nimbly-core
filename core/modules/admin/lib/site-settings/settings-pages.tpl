    <div class="py-5" x-data="{ types: [#_ss.page_types_json#] }">
        <label class="flex cursor-pointer items-center justify-between gap-4">
            <span>
                <strong class="block text-sm font-medium">[#text Custom pages#]</strong>
                <span class="text-sm text-neutral-500">[#text While on, editors can add pages. Turn off to take all custom pages offline; nothing is deleted.#]</span>
            </span>
            <input type="checkbox" class="toggle toggle-primary" x-model="features.pages" aria-label="[#text Enable custom pages#]">
        </label>
        <div x-show="features.pages" x-cloak class="mt-4">
            <p class="text-sm text-neutral-600">[#text Choose the page types editors can use for new pages. Pages that already exist keep theirs.#]</p>
            <fieldset class="mt-3 flex flex-wrap gap-3">
                <legend class="sr-only">[#text Available page types#]</legend>
                <template x-for="type in types" :key="type.id">
                    <label class="flex min-w-56 flex-1 cursor-pointer gap-3 rounded-lg border border-neutral-200 p-3">
                        <input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-1" :value="type.id" x-model="features.page_types">
                        <span><strong class="block text-sm" x-text="type.name"></strong><span class="block text-sm text-neutral-500" x-text="type.description"></span></span>
                    </label>
                </template>
            </fieldset>
            <p class="alert alert-warning mt-3 text-sm" x-show="features.page_types.length === 0">[#text No page type selected: editors can't add new pages. Existing pages stay as they are.#]</p>
        </div>
    </div>
