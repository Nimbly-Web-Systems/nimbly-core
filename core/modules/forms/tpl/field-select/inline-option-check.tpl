<label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-base-200 first:pt-4"
    data-option-label="[#text [#opt.label#]#]"
    x-show="!query || $el.dataset.optionLabel.toLowerCase().includes(query.toLowerCase()) || form_data['[#_f.key#]'].includes('[#opt.code#]')">
    <input type="checkbox" class="checkbox checkbox-sm" value="[#opt.code#]"
        x-model="form_data['[#_f.key#]']">
    <span class="text-sm" data-option-label="[#text [#opt.label#]#]"
        x-html="highlight_option($el.dataset.optionLabel)"></span>
</label>
