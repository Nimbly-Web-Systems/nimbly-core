<form x-data="site_settings_general([#_ss.name_json#], [#_ss.description_json#], [#_ss.side_json#], [#_ss.languages_json#])" @submit.prevent="submit" class="max-w-2xl space-y-4">
    <div class="mb-6 border-b border-neutral-200 pb-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-500">[#text Content language#]</p>
        <div x-show="languages.length > 1" class="flex flex-wrap" role="tablist">
            <template x-for="language in languages" :key="language"><button type="button" role="tab" @click="active_language = language" :aria-selected="active_language === language" :class="active_language === language ? 'border-b-primary font-semibold text-neutral-900' : 'border-b-transparent text-neutral-500'" class="cursor-pointer border-b-2 px-4 py-2 text-xs uppercase" x-text="language"></button></template>
        </div>
        <p x-show="languages.length < 2" class="text-sm text-neutral-500" x-text="languages[0] || '[#text No language configured#]'"></p>
    </div>
    <div><label :for="languages.length ? 'site_name_' + active_language : 'site_name'" class="mb-1 block text-sm font-medium">[#text Site name#]</label><input type="text" :id="languages.length ? 'site_name_' + active_language : 'site_name'" :value="current_name" @input="set_current('name', $event.target.value)" class="input input-bordered w-full"></div>
    <div><label :for="languages.length ? 'site_description_' + active_language : 'site_description'" class="mb-1 block text-sm font-medium">[#text Description#]</label><textarea :id="languages.length ? 'site_description_' + active_language : 'site_description'" :value="current_description" @input="set_current('description', $event.target.value)" rows="4" class="textarea textarea-bordered w-full"></textarea></div>
    <div><label :for="languages.length ? 'nimblybar_side_' + active_language : 'nimblybar_side'" class="mb-1 block text-sm font-medium">[#text Admin sidebar position#]</label><select :id="languages.length ? 'nimblybar_side_' + active_language : 'nimblybar_side'" :value="current_side" @change="set_current('side', $event.target.value)" class="select select-bordered w-full"><option value="left">[#text Left#]</option><option value="right">[#text Right#]</option></select></div>
    <div class="flex items-center gap-3 pt-2"><button type="submit" class="[#btn-class-primary#]" :disabled="busy" x-text="busy ? '[#text Saving…#]' : '[#text Save#]'"></button><span class="text-sm text-success" x-show="saved" x-cloak>[#text Settings saved#]</span></div>
</form>
