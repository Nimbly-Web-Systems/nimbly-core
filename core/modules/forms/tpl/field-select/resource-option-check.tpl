<label class="flex items-center gap-3 px-3 py-2 hover:bg-base-200 cursor-pointer first:pt-4" @mousedown.prevent>
    <input type="checkbox" class="checkbox checkbox-sm"
        :checked="(form_data['[#_f.key#]'] || '').split(',').includes('[#opt.key#]')"
        @change="
            let s = (form_data['[#_f.key#]'] || '').split(',').filter(v => v && v !== '(empty)');
            const i = s.indexOf('[#opt.key#]');
            i === -1 ? s.push('[#opt.key#]') : s.splice(i, 1);
            form_data['[#_f.key#]'] = s.join(',') || '(empty)';
        ">
    <span class="text-sm">[#get opt.name#][#get opt.title#]</span>
</label>
