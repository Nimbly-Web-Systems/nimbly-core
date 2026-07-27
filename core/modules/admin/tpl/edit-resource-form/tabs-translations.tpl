<div class="mb-10 flex flex-wrap items-center justify-between gap-3">
    <ul class="flex flex-row">
        <li><button type="button"
            @click="save(); lang='[#get record.lang#]'"
            class="uppercase text-xs px-4 py-2 border-b-2 hover:font-bold hover:text-black"
            :class="lang=='[#get record.lang#]'? 'border-b-primary' : 'border-b-transparent'">[#text [#get record.lang#]#]</button></li>
        [#repeat languages tpl=tab-language#]
    </ul>
    <button type="button" x-cloak x-show="_ai_translate_record && _translation_mode === 'field'"
        class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100 disabled:pointer-events-none disabled:opacity-70"
        :disabled="busy" title="[#text Translate entire record with AI#]"
        @click.prevent="ai_all(lang)">
        <span class="inline-flex" :class="{ 'animate-spin': ai_busy_all }">[#include file=[#base-path#]core/modules/forms/lib/field-actions/icon-sparkles.tpl#]</span>
        <span>[#text Translate entire record#]</span>
    </button>
</div>
