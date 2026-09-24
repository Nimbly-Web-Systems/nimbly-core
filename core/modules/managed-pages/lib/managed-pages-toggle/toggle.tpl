<form x-data="managed_pages_admin([#_mp.page_types_json#], [#_mp.enabled_page_types_json#], [#_mp.enabled_json#])" @submit.prevent="submit"
    class="mt-4 rounded-box border border-base-300 bg-base-100 p-3 sm:p-4">
    <label class="flex cursor-pointer items-center justify-between gap-4">
        <span>
            <strong class="block text-sm">[#text Custom pages#]</strong>
            <span class="text-sm text-neutral-500">[#text While on, editors can add pages and edit the navigation. Turn off to take all custom pages offline; nothing is deleted.#]</span>
        </span>
        <input type="checkbox" class="toggle toggle-primary" x-model="pages_enabled" aria-label="[#text Enable custom pages#]">
    </label>
    <div x-show="pages_enabled" x-cloak class="mt-4">
        <p class="text-sm text-neutral-600">[#text Choose which page types editors may use for new pages. Existing pages keep their type when it is disabled here.#]</p>
        <fieldset class="mt-3 flex flex-wrap gap-3">
            <legend class="sr-only">[#text Available page types#]</legend>
            <template x-for="type in types" :key="type.id">
                <label class="flex min-w-56 flex-1 cursor-pointer gap-3 rounded-lg border border-neutral-200 p-3">
                    <input type="checkbox" class="checkbox checkbox-primary checkbox-sm mt-1" :value="type.id" x-model="enabled">
                    <span><strong class="block text-sm" x-text="type.name"></strong><span class="block text-sm text-neutral-500" x-text="type.description"></span></span>
                </label>
            </template>
        </fieldset>
        <p class="alert alert-warning mt-3 text-sm" x-show="enabled.length === 0">[#text With no page types enabled, editors cannot create new pages. Existing pages remain available.#]</p>
    </div>
    <div class="mt-4 flex items-center gap-3" x-show="dirty" x-cloak>
        <button type="submit" class="[#btn-class-primary#]" :disabled="busy">[#text Save#]</button>
    </div>
</form>
<script>[#include file=[#base-path#]core/modules/managed-pages/lib/managed-pages-toggle/managed-pages-toggle.js#]</script>
