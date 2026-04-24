<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 px-4 pt-4 pb-3"
    x-init="[#_f.model#]='[#get [#get _f.source default=record#].[#_f.key#]#]'">

    <label class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 -translate-y-[10px] bg-neutral-50">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>

    <div x-data="{ _meta: null }"
         x-effect="
             const _uuid = [#_f.model#];
             if (_uuid && (!_meta || _meta.uuid !== _uuid)) {
                 const _d = $data;
                 nb.api.get(nb.base_url + '/api/v1/.files_meta/' + _uuid).then(r => {
                     if (r.success) _d._meta = r['.files_meta'][_uuid] || null;
                 });
             } else if (!_uuid) {
                 _meta = null;
             }
         "
         class="flex items-center gap-3 min-h-[40px]">

        <!-- document icon -->
        <svg x-show="[#_f.model#]"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="w-6 h-6 text-neutral-400 shrink-0">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9z" />
        </svg>

        <!-- file info: title + size -->
        <div x-show="[#_f.model#]" class="flex flex-col min-w-0 flex-1 gap-0.5">
            <span class="text-sm text-neutral-800 truncate leading-tight"
                x-text="_meta ? (_meta.title || _meta.name) : ''">
            </span>
            <span class="text-xs text-neutral-400 leading-none"
                x-text="_meta && _meta.size ? (s => s < 1024 ? s + ' B' : s < 1048576 ? (s / 1024).toFixed(1) + ' KB' : (s / 1048576).toFixed(1) + ' MB')(_meta.size) : ''">
            </span>
        </div>

        <!-- empty state -->
        <p x-show="[#_f.model#] == ''" class="flex-1 text-sm text-neutral-400">
            [#text No file selected#]
        </p>

        <!-- actions -->
        <div class="flex items-center shrink-0 ml-auto">

            <!-- view in browser -->
            <a x-show="[#_f.model#]"
                :href="`${nb.base_url}/download/${[#_f.model#]}`" target="_blank"
                class="[#btn-class-icon#] p-1 text-neutral-600"
                title="[#text View#]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                </svg>
            </a>

            <!-- force download -->
            <a x-show="[#_f.model#]"
                :href="`${nb.base_url}/download/${[#_f.model#]}`"
                :download="_meta ? _meta.name : ''"
                class="[#btn-class-icon#] p-1 text-neutral-600"
                title="[#text Download#]">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
            </a>

            <!-- pick from media library — calls select_image (modal_settings method via scope
                 chain) then overrides the type filter to show documents instead of images -->
            <button class="[#btn-class-icon#] p-1 text-neutral-600"
                data-te-toggle="modal" data-te-target="#nb-modal-insert-media"
                title="[#text Select file#]"
                @click.prevent="
                    select_image('[#_f.key#]');
                    nb.media_alpine.filter(['doc']);
                ">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                </svg>
            </button>

            <!-- clear -->
            <button class="[#btn-class-icon#] p-1 text-neutral-600
                disabled:pointer-events-none disabled:text-neutral-200"
                :disabled="![#_f.model#]"
                title="[#text Delete#]"
                @click.prevent="[#_f.model#] = ''; _meta = null;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </button>

        </div>
    </div>

</div>
