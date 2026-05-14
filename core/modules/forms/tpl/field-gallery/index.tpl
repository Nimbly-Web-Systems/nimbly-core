<script>var _nb_gallery_[#_f.key#] = [#fmt var=[#get _f.source default=record#].[#_f.key#] type=json empty=[]#];</script>
<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4"
    x-init="[#_f.model#] = window._nb_gallery_[#_f.key#] || []; delete window._nb_gallery_[#_f.key#]">

    <label class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 -translate-y-[10px] bg-neutral-50">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>

    <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-2"
         x-data="{ drag_ix: null, drop_ix: null }"
         @dragleave="if (!$el.contains($event.relatedTarget)) drop_ix = null">

        <template x-for="(uuid, ix) in [#_f.model#]" :key="uuid + ix">
            <div class="flex flex-col rounded overflow-hidden border bg-white transition-all"
                 draggable="true"
                 @dragstart="drag_ix = ix"
                 @dragenter.prevent="drop_ix = ix"
                 @dragover.prevent
                 @drop.prevent="move_item('[#_f.key#]', drag_ix, ix); drag_ix = null; drop_ix = null"
                 @dragend="drag_ix = null; drop_ix = null"
                 :class="{
                     'opacity-40 border-neutral-200 cursor-grabbing': drag_ix === ix,
                     'border-primary ring-2 ring-primary bg-primary/5': drop_ix === ix && drag_ix !== ix,
                     'border-neutral-200 cursor-grab': drag_ix !== ix && drop_ix !== ix
                 }">

                <!-- thumbnail -->
                <div class="aspect-square bg-neutral-100 relative overflow-hidden">
                    <img :src="`[#base-url#]/img/${uuid}/480x480f`"
                         class="w-full h-full object-cover pointer-events-none">
                    <span class="absolute top-1 left-1 text-white text-xs bg-black/50 px-1 rounded"
                          x-text="ix + 1"></span>
                </div>

                <!-- controls -->
                <div class="flex items-center justify-between px-1 py-0.5 bg-neutral-100 border-t border-neutral-200">
                    <div class="flex">
                        <button class="btn btn-xs btn-ghost px-1" draggable="false"
                            @click.prevent="move_item('[#_f.key#]', ix, ix - 1)"
                            :disabled="ix === 0" title="[#text Move left#]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                        </button>
                        <button class="btn btn-xs btn-ghost px-1" draggable="false"
                            @click.prevent="move_item('[#_f.key#]', ix, ix + 1)"
                            :disabled="ix === [#_f.model#].length - 1" title="[#text Move right#]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                        </button>
                    </div>
                    <div class="flex">
                        <button class="btn btn-xs btn-ghost px-1" draggable="false"
                            @click.prevent="select_image('[#_f.key#]', ix); nb.modal.open('nb-modal-insert-media')"
                            title="[#text Change image#]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
                        </button>
                        <button class="btn btn-xs btn-ghost px-1 text-error" draggable="false"
                            @click.prevent="[#_f.model#].splice(ix, 1)"
                            title="[#text Delete#]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        </button>
                    </div>
                </div>

            </div>
        </template>

        <!-- add image card — always last -->
        <button class="flex flex-col items-center justify-center aspect-square rounded border-2 border-dashed
                border-neutral-300 bg-white text-neutral-400 hover:border-primary hover:text-primary transition-colors"
            @click.prevent="select_image('[#_f.key#]', [#_f.model#].length); nb.modal.open('nb-modal-insert-media')">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mb-1" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span class="text-xs font-medium">[#text Add image#]</span>
        </button>

    </div>

</div>
