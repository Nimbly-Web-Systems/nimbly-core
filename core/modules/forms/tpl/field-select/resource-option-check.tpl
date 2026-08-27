<label class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-base-200 first:pt-4"
    data-option-label="[#resource-title resource=[#_f.resource#] uuid=[#opt.key#]#]"
    x-show="!query || $el.dataset.optionLabel.toLowerCase().includes(query.toLowerCase()) || form_data['[#_f.key#]'].includes('[#opt.key#]')">
    <input type="checkbox" class="checkbox checkbox-sm" value="[#opt.key#]"
        x-model="form_data['[#_f.key#]']">
    <span class="text-sm">[#resource-title resource=[#_f.resource#] uuid=[#opt.key#]#]</span>
</label>
