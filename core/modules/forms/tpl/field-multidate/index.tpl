<script>var _nb_multidate_[#_f.key#] = [#fmt var=[#get _f.source default=record#].[#_f.key#] type=json empty=[]#];</script>
<div class="relative my-10 border border-neutral-300 rounded bg-neutral-50 p-4"
    x-data="{ new_date: '' }"
    x-init="const _raw = window._nb_multidate_[#_f.key#]; form_data['[#_f.key#]'] = Array.isArray(_raw) ? _raw : (_raw ? [_raw] : []); delete window._nb_multidate_[#_f.key#]">

    <label class="pointer-events-none absolute left-3 top-0 text-sm font-bold px-1
            text-neutral-800 -translate-y-[10px] bg-neutral-50">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>

    <div class="flex flex-col gap-1 mt-1">
        <template x-for="d in form_data['[#_f.key#]']" :key="d">
            <div class="flex items-center justify-between bg-white border border-neutral-200 rounded px-3 py-1.5">
                <span class="font-mono text-sm" x-text="d"></span>
                <button type="button"
                    @click="form_data['[#_f.key#]'].splice(form_data['[#_f.key#]'].indexOf(d), 1)"
                    class="btn btn-xs btn-ghost text-error">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </template>
    </div>

    <div class="flex gap-2 mt-3">
        <input type="date" x-model="new_date" class="input input-bordered input-sm flex-1" />
        <button type="button" class="btn btn-sm btn-neutral"
            @click="
                if (!new_date || form_data['[#_f.key#]'].includes(new_date)) return;
                form_data['[#_f.key#]'].push(new_date);
                form_data['[#_f.key#]'].sort();
                new_date = '';
            ">
            + Add
        </button>
    </div>

</div>
