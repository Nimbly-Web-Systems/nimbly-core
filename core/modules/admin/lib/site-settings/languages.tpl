<div x-data="site_settings_languages([#_ss.language_rows_json#], [#_ss.language_catalog_json#])" class="max-w-2xl">
    <p class="text-sm text-neutral-600">[#text Visitors see the site in their own language when it is available, and in the default language when it is not.#]</p>
    <ol class="mt-5 divide-y divide-neutral-200 rounded-lg border border-neutral-200"><template x-for="(language, index) in configured" :key="language.code"><li class="flex items-center justify-between gap-4 p-3"><span><strong x-text="language.label"></strong> <span class="ml-2 text-xs uppercase text-neutral-500" x-text="language.code"></span></span><span class="badge badge-ghost" x-show="index === 0">[#text Default#]</span></li></template></ol>
    <form @submit.prevent="submit" class="mt-6 rounded-lg border border-neutral-200 p-4">
        <label for="site_language_add" class="mb-1 block text-sm font-medium">[#text Add language#]</label>
        <div class="flex flex-col gap-3 sm:flex-row"><select id="site_language_add" x-model="new_language" class="select select-bordered flex-1" required><option value="">[#text Choose a language#]</option><template x-for="option in available" :key="option.code"><option :value="option.code" x-text="option.label + ' (' + option.code + ')'"> </option></template></select><button type="submit" class="[#btn-class-primary#]" :disabled="busy || !new_language" x-text="busy ? '[#text Saving…#]' : '[#text Add#]'"></button></div>
        <p class="mt-3 text-xs text-neutral-500">[#text A new language starts empty. Add its translations to your pages and menus.#]</p><p class="mt-3 text-sm text-success" x-show="saved" x-cloak>[#text Language added.#]</p>
    </form>
</div>
