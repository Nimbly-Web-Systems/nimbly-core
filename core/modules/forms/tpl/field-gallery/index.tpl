<script>var _nb_gallery_[#_f.key#] = [#fmt var=[#get _f.source default=record#].[#_f.key#] type=json empty=[]#];</script>
<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4"
    x-init="[#_f.model#] = window._nb_gallery_[#_f.key#] || []; delete window._nb_gallery_[#_f.key#]">

    <label class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 -translate-y-[10px] bg-neutral-50">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>

    <!-- thumbnail grid -->
    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2 mt-2 mb-4"
        x-show="[#_f.model#].length > 0">
        <template x-for="(uuid, ix) in [#_f.model#]" :key="uuid + ix">
            <div class="relative aspect-square bg-neutral-100 rounded overflow-hidden group">
                <img :src="`[#base-url#]/img/${uuid}/480x480f`" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100
                        transition-opacity flex flex-col items-center justify-center gap-1">
                    <div class="flex gap-1">
                        <button class="btn btn-xs btn-ghost text-white"
                            @click.prevent="move_item('[#_f.key#]', ix, ix - 1)"
                            :disabled="ix === 0">↑</button>
                        <button class="btn btn-xs btn-ghost text-white"
                            @click.prevent="move_item('[#_f.key#]', ix, ix + 1)"
                            :disabled="ix === [#_f.model#].length - 1">↓</button>
                    </div>
                    <button class="btn btn-xs btn-error"
                        @click.prevent="[#_f.model#].splice(ix, 1)">✕</button>
                </div>
                <span class="absolute bottom-1 left-1 text-white text-xs bg-black/50 px-1 rounded"
                    x-text="ix + 1"></span>
            </div>
        </template>
    </div>

    <button class="btn btn-sm btn-outline"
        data-te-toggle="modal" data-te-target="#nb-modal-insert-media"
        @click.prevent="select_image('[#_f.key#]', [#_f.model#].length)">
        + [#text Add image#]
    </button>

</div>
