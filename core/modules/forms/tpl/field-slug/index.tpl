<div x-data="{ no_prefix: [#_f.slug_allow_unprefixed#] && [#_f.slug_language_prefix#] && !!([#_f.model#]) && !!lang && !([#_f.model#]).startsWith(lang + '/') }"
     [#_f.x_init#]
     x-effect="
         const _parts = '[#_f.source#]'.split(',').map(f => {
             const _value = form_data[f.trim()] || '';
             return _value && typeof _value === 'object' ? (_value[lang] || '') : _value;
         }).join(' ');
         const _prefix = [#_f.slug_language_prefix#] && lang && !no_prefix && (nb.languages.length > 1 || [#_f.slug_allow_unprefixed#] === false) ? `${lang}/` : '';
         [#_f.model#] = _prefix + slugify(_parts);
     "
     class="[#_f.wrapper_class#] max-w-md">
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
    <label x-show="[#_f.slug_allow_unprefixed#] && nb.languages.length > 1" x-cloak class="mt-2 flex cursor-pointer items-center gap-2 text-sm text-neutral-600">
        <input type="checkbox" class="checkbox checkbox-xs" x-model="no_prefix">
        <span>[#text Publish without a language prefix#]</span>
    </label>
</div>
