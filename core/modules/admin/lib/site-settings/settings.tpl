<form x-data="site_settings([#_ss.name_json#], [#_ss.description_json#], [#_ss.direction_json#], [#_ss.side_json#], [#_ss.language_rows_json#], [#_ss.language_catalog_json#], [#_ss.rtl_json#], [#_ss.features_json#])" @submit.prevent="submit" class="max-w-2xl divide-y divide-neutral-200">
    <div class="pb-5">
        <label :for="'site_name_' + active.name" class="mb-1 block text-sm font-medium">[#text Site name#]</label>
        <ul class="mb-1.5 flex flex-row" x-show="codes.length > 1" role="group" aria-label="[#text Language of the site name#]"><template x-for="code in codes" :key="code"><li><button type="button" class="cursor-pointer border-b-2 px-2 py-1 text-xs uppercase text-gray-600 hover:font-bold hover:text-black" :class="active.name === code ? 'border-b-primary' : 'border-b-transparent'" :aria-pressed="active.name === code" @click="active.name = code" x-text="code"></button></li></template></ul>
        <input type="text" :id="'site_name_' + active.name" :value="value('name')" @input="set_value('name', $event.target.value)" class="input input-bordered w-full">
    </div>
    <div class="py-5">
        <label :for="'site_description_' + active.description" class="mb-1 block text-sm font-medium">[#text Description#]</label>
        <ul class="mb-1.5 flex flex-row" x-show="codes.length > 1" role="group" aria-label="[#text Language of the description#]"><template x-for="code in codes" :key="code"><li><button type="button" class="cursor-pointer border-b-2 px-2 py-1 text-xs uppercase text-gray-600 hover:font-bold hover:text-black" :class="active.description === code ? 'border-b-primary' : 'border-b-transparent'" :aria-pressed="active.description === code" @click="active.description = code" x-text="code"></button></li></template></ul>
        <textarea :id="'site_description_' + active.description" :value="value('description')" @input="set_value('description', $event.target.value)" rows="3" class="textarea textarea-bordered w-full"></textarea>
    </div>
    <div class="py-5">
        <p class="mb-2 text-sm font-medium">[#text Languages#]</p>
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
    </div>
    [#_ss.pages_row#]
    [#_ss.navigation_row#]
    <div class="py-5">
        <label :for="'text_direction_' + active.direction" class="mb-1 block text-sm font-medium">[#text Text direction#]</label>
        <ul class="mb-1.5 flex flex-row" x-show="codes.length > 1" role="group" aria-label="[#text str="Language of the text direction"#]"><template x-for="code in codes" :key="code"><li><button type="button" class="cursor-pointer border-b-2 px-2 py-1 text-xs uppercase text-gray-600 hover:font-bold hover:text-black" :class="active.direction === code ? 'border-b-primary' : 'border-b-transparent'" :aria-pressed="active.direction === code" @click="active.direction = code" x-text="code"></button></li></template></ul>
        <select :id="'text_direction_' + active.direction" :value="value('direction')" @change="set_value('direction', $event.target.value)" class="select select-bordered w-auto"><option value="ltr">[#text Left to right#]</option><option value="rtl">[#text Right to left#]</option></select>
    </div>
    <div class="py-5">
        <label :for="'nimblybar_side_' + active.side" class="mb-1 block text-sm font-medium">[#text Admin sidebar position#]</label>
        <ul class="mb-1.5 flex flex-row" x-show="codes.length > 1" role="group" aria-label="[#text Language of the sidebar position#]"><template x-for="code in codes" :key="code"><li><button type="button" class="cursor-pointer border-b-2 px-2 py-1 text-xs uppercase text-gray-600 hover:font-bold hover:text-black" :class="active.side === code ? 'border-b-primary' : 'border-b-transparent'" :aria-pressed="active.side === code" @click="active.side = code" x-text="code"></button></li></template></ul>
        <select :id="'nimblybar_side_' + active.side" :value="value('side')" @change="set_value('side', $event.target.value)" class="select select-bordered w-auto"><option value="left">[#text Left#]</option><option value="right">[#text Right#]</option></select>
    </div>
    <div class="flex items-center gap-3 pt-5"><button type="submit" class="[#btn-class-primary#]" :disabled="busy || !dirty" x-text="busy ? '[#text Saving…#]' : '[#text Save#]'"></button><span class="text-sm text-neutral-500" x-show="dirty" x-cloak>[#text Unsaved changes#]</span></div>
</form>
