<div x-init="form_data['[#_f.key#]'] = '[#_f.value#]'"
     x-effect="
         const _parts = '[#_f.source#]'.split(',').map(f => form_data[f.trim()] || '').join(' ');
         form_data['[#_f.key#]'] = slugify(_parts);
     "
     class="relative my-10">
    <input type="text"
        name="[#_f.key#]"
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        placeholder=""
        class="input input-bordered w-full font-mono text-sm" />
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
