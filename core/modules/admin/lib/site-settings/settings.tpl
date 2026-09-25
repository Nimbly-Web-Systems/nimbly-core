<form x-data="site_settings([#_ss.name_json#], [#_ss.description_json#], [#_ss.direction_json#], [#_ss.side_json#], [#_ss.language_rows_json#], [#_ss.language_catalog_json#], [#_ss.rtl_json#], [#_ss.features_json#])" @submit.prevent="submit" class="max-w-2xl divide-y divide-neutral-200">
    <div class="pb-5">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <label :for="'site_name_' + active.name" class="text-sm font-medium">[#text Site name#]</label>
            <div class="join" x-show="codes.length > 1" role="group" aria-label="[#text Language of the site name#]"><template x-for="code in codes" :key="code"><button type="button" class="btn join-item btn-xs uppercase" :class="active.name === code && 'btn-active'" :aria-pressed="active.name === code" @click="active.name = code" x-text="code"></button></template></div>
        </div>
        <input type="text" :id="'site_name_' + active.name" :value="value('name')" @input="set_value('name', $event.target.value)" class="input input-bordered w-full">
    </div>
    <div class="py-5">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <label :for="'site_description_' + active.description" class="text-sm font-medium">[#text Description#]</label>
            <div class="join" x-show="codes.length > 1" role="group" aria-label="[#text Language of the description#]"><template x-for="code in codes" :key="code"><button type="button" class="btn join-item btn-xs uppercase" :class="active.description === code && 'btn-active'" :aria-pressed="active.description === code" @click="active.description = code" x-text="code"></button></template></div>
        </div>
        <textarea :id="'site_description_' + active.description" :value="value('description')" @input="set_value('description', $event.target.value)" rows="3" class="textarea textarea-bordered w-full"></textarea>
    </div>
    <div class="py-5">
        <p class="mb-1 text-sm font-medium">[#text Languages#]</p>
        <p class="mb-3 text-sm text-neutral-500">[#text str="Visitors see the site in their own language when it is available, and in the default language when it is not."#]</p>
        <div class="flex flex-wrap items-center gap-2">
            <template x-for="(language, index) in languages" :key="language.code">
                <div class="dropdown">
                    <button type="button" tabindex="0" class="btn btn-sm rounded-full" :class="index === 0 ? 'btn-primary' : 'btn-outline'" :disabled="busy || dirty" :title="index === 0 ? '[#text Default language#]' : ''">
                        <span x-text="language.label"></span><span class="text-xs uppercase opacity-70" x-text="language.code"></span><span class="badge badge-xs" x-show="index === 0">[#text Default#]</span>
                    </button>
                    <ul tabindex="0" class="dropdown-content menu z-10 mt-1 w-44 rounded-box bg-base-100 p-2 shadow" x-show="index > 0"><li><button type="button" @click="make_default(language.code)">[#text Make default#]</button></li></ul>
                </div>
            </template>
            <select x-model="new_language" @change="add_language()" :disabled="busy || dirty" class="select select-bordered select-sm w-auto" aria-label="[#text Add language#]">
                <option value="">+ [#text Add language#]</option>
                <template x-for="option in available" :key="option.code"><option :value="option.code" x-text="option.label + ' (' + option.code + ')'"></option></template>
            </select>
        </div>
        <p class="mt-2 text-xs text-warning" x-show="dirty" x-cloak>[#text Save your other changes before changing languages.#]</p>
        <p class="mt-2 text-xs text-neutral-500">[#text Language changes are saved right away. A new language starts empty: add its translations to your pages and menus.#]</p>
    </div>
    [#_ss.pages_row#]
    [#_ss.navigation_row#]
    <div class="py-5">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <label :for="'text_direction_' + active.direction" class="text-sm font-medium">[#text Text direction#]</label>
            <div class="join" x-show="codes.length > 1" role="group" aria-label="[#text str="Language of the text direction"#]"><template x-for="code in codes" :key="code"><button type="button" class="btn join-item btn-xs uppercase" :class="active.direction === code && 'btn-active'" :aria-pressed="active.direction === code" @click="active.direction = code" x-text="code"></button></template></div>
        </div>
        <select :id="'text_direction_' + active.direction" :value="value('direction')" @change="set_value('direction', $event.target.value)" class="select select-bordered w-full"><option value="ltr">[#text Left to right#]</option><option value="rtl">[#text Right to left#]</option></select>
        <p class="mt-1 text-xs text-neutral-500">[#text str="How the site's text runs in this language. Arabic, Persian and Hebrew start right to left."#]</p>
    </div>
    <div class="py-5">
        <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
            <label :for="'nimblybar_side_' + active.side" class="text-sm font-medium">[#text Admin sidebar position#]</label>
            <div class="join" x-show="codes.length > 1" role="group" aria-label="[#text Language of the sidebar position#]"><template x-for="code in codes" :key="code"><button type="button" class="btn join-item btn-xs uppercase" :class="active.side === code && 'btn-active'" :aria-pressed="active.side === code" @click="active.side = code" x-text="code"></button></template></div>
        </div>
        <select :id="'nimblybar_side_' + active.side" :value="value('side')" @change="set_value('side', $event.target.value)" class="select select-bordered w-full"><option value="left">[#text Left#]</option><option value="right">[#text Right#]</option></select>
        <p class="mt-1 text-xs text-neutral-500">[#text str="Set per language. Right-to-left languages start with the sidebar on the right."#]</p>
    </div>
    <div class="flex items-center gap-3 pt-5"><button type="submit" class="[#btn-class-primary#]" :disabled="busy || !dirty" x-text="busy ? '[#text Saving…#]' : '[#text Save#]'"></button><span class="text-sm text-neutral-500" x-show="dirty" x-cloak>[#text Unsaved changes#]</span></div>
</form>
