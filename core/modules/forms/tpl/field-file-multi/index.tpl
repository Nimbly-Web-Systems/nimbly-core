<div class="relative my-6 rounded border border-neutral-300 bg-neutral-50 px-4 pb-3 pt-5" [#_f.x_init#]>
    <label class="pointer-events-none absolute left-3 top-0 -translate-y-[10px] bg-neutral-50 px-1 text-sm text-neutral-600">
        [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
    </label>
    <div class="divide-y divide-neutral-200">
        <template x-for="(uuid, ix) in [#_f.model#]" :key="uuid + ix">
            <div class="flex min-h-12 items-center gap-3 py-2" x-data="{ meta: null }"
                x-init="nb.api.get(nb.base_url + '/api/v1/.files_meta/' + uuid).then(r => meta = r.success ? (r['.files_meta'][uuid] || null) : null)">
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm text-neutral-800" x-text="meta ? (nb.media_library._resolve_i18n(meta.title) || meta.name) : uuid"></div>
                    <div class="text-xs text-neutral-400" x-text="meta && meta.size ? (s => s < 1024 ? s + ' B' : s < 1048576 ? (s / 1024).toFixed(1) + ' KB' : (s / 1048576).toFixed(1) + ' MB')(meta.size) : ''"></div>
                </div>
                <a :href="`${nb.base_url}/download/${uuid}`" target="_blank"
                    class="[#btn-class-icon#] p-1 text-neutral-600" title="[#text View#]">[#text View#]</a>
                <button type="button" class="[#btn-class-icon#] p-1 text-neutral-600" title="[#text Change file#]"
                    @click.prevent="select_image('[#_f.key#]', ix); nb.media_alpine.filter(['doc']); nb.modal.open('nb-modal-insert-media')">[#text Change#]</button>
                <button type="button" class="[#btn-class-icon#] p-1 text-error" title="[#text Delete#]"
                    @click.prevent="[#_f.model#].splice(ix, 1)" aria-label="[#text Delete#]">×</button>
            </div>
        </template>
    </div>
    <p x-show="[#_f.model#].length === 0" class="py-2 text-sm text-neutral-400">[#text No files selected#]</p>
    <button type="button"
        class="mt-2 inline-flex cursor-pointer items-center gap-2 rounded border border-dashed border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-600 hover:border-neutral-500 hover:text-neutral-900"
        @click.prevent="select_image('[#_f.key#]', [#_f.model#].length); nb.media_alpine.filter(['doc']); nb.modal.open('nb-modal-insert-media')">
        <span aria-hidden="true">+</span> [#text Add file#]
    </button>
</div>
