<form x-data="site_settings([#_ss.name_json#], [#_ss.description_json#], [#_ss.side_json#], [#_ss.languages_json#])" @submit.prevent="submit"
    class="overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
    <div class="max-w-lg space-y-4 p-5">
        <ul x-show="languages.length > 1" class="mb-10 flex flex-row" role="tablist">
            <template x-for="language in languages" :key="language">
                <li>
                    <button type="button" role="tab" @click="active_language = language"
                        :aria-selected="active_language === language"
                        :class="active_language === language ? 'border-b-primary' : 'border-b-transparent'"
                        class="cursor-pointer border-b-2 px-4 py-2 text-xs uppercase text-gray-600 hover:font-bold hover:text-black"
                        x-text="language"></button>
                </li>
            </template>
        </ul>
        <div>
            <label :for="languages.length ? 'site_name_' + active_language : 'site_name'"
                class="mb-1 block text-sm font-medium text-neutral-700">[#text Site name#]</label>
            <input type="text" :id="languages.length ? 'site_name_' + active_language : 'site_name'"
                :value="current_name" @input="set_current('name', $event.target.value)" class="input input-bordered w-full">
        </div>
        <div>
            <label :for="languages.length ? 'site_description_' + active_language : 'site_description'"
                class="mb-1 block text-sm font-medium text-neutral-700">[#text Description#]</label>
            <textarea :id="languages.length ? 'site_description_' + active_language : 'site_description'"
                :value="current_description" @input="set_current('description', $event.target.value)" rows="4"
                class="textarea textarea-bordered w-full"></textarea>
        </div>
        <div>
            <label :for="languages.length ? 'nimblybar_side_' + active_language : 'nimblybar_side'"
                class="mb-1 block text-sm font-medium text-neutral-700">[#text Admin sidebar position#]</label>
            <select :id="languages.length ? 'nimblybar_side_' + active_language : 'nimblybar_side'"
                :value="current_side" @change="set_current('side', $event.target.value)" class="select select-bordered w-full">
                <option value="left">[#text Left#]</option>
                <option value="right">[#text Right#]</option>
            </select>
        </div>

        <div class="flex flex-row items-center gap-4 pt-2">
            <button type="submit" class="[#btn-class-primary#] flex flex-row items-center align-middle" x-bind:disabled="busy">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-spin"
                    x-cloak x-show="busy">
                    <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                        d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                        fill="#ffffff" />
                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#ffffff" />
                </svg>
                <span class="px-1">[#text Save#]</span>
            </button>
        </div>
    </div>

    <script>
        [#include file=[#base-path#]core/modules/admin/lib/site-settings/site-settings.js#]
    </script>
</form>
