<div x-data="{
    _auto: [#if _f.value=(empty) echo=true tpl_else=false#],
    slugify(val) {
        return String(val).toLowerCase().trim()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^0-9a-z]+/g, '-')
            .replace(/^-+|-+$/g, '');
    },
    recompute() {
        if (!this._auto) return;
        const parts = '[#_f.source#]'.split(',')
            .map(f => this.form_data[f.trim()] || '').join(' ');
        this.form_data['[#_f.key#]'] = this.slugify(parts);
    }
}" x-init="
    form_data['[#_f.key#]'] = '[#_f.value#]';
    if ('[#_f.source#]') {
        '[#_f.source#]'.split(',').forEach(f => $watch('form_data.' + f.trim(), () => recompute()));
    }
" class="relative my-10">
    <input type="text"
        name="[#_f.key#]"
        x-model="[#_f.model#]"
        @input="_auto = false"
        @change="if (!$el.value) { _auto = true; recompute(); }"
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
