<form x-data="site_settings_page_types([#_ss.page_types_json#], [#_ss.enabled_page_types_json#], [#_ss.pages_enabled_json#])" @submit.prevent="submit" class="max-w-2xl">
    <label class="flex cursor-pointer items-start justify-between gap-5 rounded-lg border border-neutral-200 p-4">
        <span><strong class="block">[#text Enable custom pages#]</strong><span class="mt-1 block text-sm text-neutral-500">[#text Show Pages in the admin resource menu and resolve published page addresses through the router fallback.#]</span></span>
        <input type="checkbox" class="toggle toggle-primary mt-1" x-model="pages_enabled" aria-label="[#text Enable custom pages#]">
    </label>
    <div x-show="pages_enabled" x-cloak>
        <p class="mt-5 text-sm text-neutral-600">[#text Choose which crafted page templates editors may use for new pages. Existing pages keep their template when it is disabled here.#]</p>
        <fieldset class="mt-4 space-y-3"><legend class="sr-only">[#text Available page templates#]</legend><template x-for="type in types" :key="type.id"><label class="flex cursor-pointer gap-3 rounded-lg border border-neutral-200 p-4"><input type="checkbox" class="checkbox checkbox-primary mt-1" :value="type.id" x-model="enabled"><span><strong class="block" x-text="type.name"></strong><span class="mt-1 block text-sm text-neutral-500" x-text="type.description"></span></span></label></template></fieldset>
        <p class="alert alert-warning mt-4 text-sm" x-show="enabled.length === 0">[#text With no templates enabled, editors cannot create new pages. Existing pages remain available.#]</p>
    </div>
    <div class="mt-5 flex items-center gap-3"><button type="submit" class="[#btn-class-primary#]" :disabled="busy" x-text="busy ? '[#text Saving…#]' : '[#text Save#]'"></button><span class="text-sm text-success" x-show="saved" x-cloak>[#text Custom pages saved#]</span></div>
</form>
