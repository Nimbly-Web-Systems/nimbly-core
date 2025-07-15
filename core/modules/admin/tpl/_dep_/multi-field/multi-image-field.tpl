<div class="relative my-6 border border-neutral-300 rounded bg-neutral-50 p-4">
    <label for="" class="pointer-events-none absolute left-3 top-0 text-sm px-1
            text-neutral-600 
            -translate-y-[10px]
            bg-neutral-50">
        [#text [#field-name name="[#item.name#]"#]#]
    </label>
    <table class="min-w-full text-left text-sm font-light" x-data="{
            _dd_table: $el, 
            _dd_src_el: null,
            _dd_cleanup_styles() {
                this._dd_table.querySelectorAll('.border-b-2', '.border-b-clight')
                .forEach(function (el) {
                    el.classList.remove('border-b-2', 'border-b-clight');
                });
                this._dd_table.querySelectorAll('.border-t-2', '.border-t-clight')
                .forEach(function (el) {
                    el.classList.remove('border-t-2', 'border-t-clight');
                });
            }
        }">
        <thead class="border-b font-medium dark:border-neutral-500">
            <tr>
                <th scope="col" class="px-4 py-2">#</th>
                <th scope="col" class="px-4 py-2">[#text Image#]</th>
                <th scope="col" class="px-4 py-2">[#text Actions#]</th>
            </tr>
        </thead>
        <tbody x-init='form_data.[#item.key#]=[#get record.[#item.key#] empty=[] json#])'
            data-nb-edit-multi-image="[#item.key#]">
            <template x-for="(img_uuid, img_ix) in form_data.[#item.key#]" :key="img_ix">
                <tr class="border-b dark:border-neutral-500 transition-all cursor-grab hover:bg-clight/10"
                    draggable="true" @dragstart="
                        _dd_src_el = $el;
                        $event.dataTransfer.effectAllowed = 'move';
                        $event.dataTransfer.setData('text/html', $el.outerHTML);
                        $el.classList.add('bg-clight/20');" @dragend="
                        $el.classList.remove('bg-clight/20');
                        _dd_cleanup_styles();
                        " @dragover.prevent="
                            if ($el.rowIndex < _dd_src_el.rowIndex) {
                                $el.classList.add('border-t-2', 'border-t-clight')
                            } if ($el.rowIndex > _dd_src_el.rowIndex) {
                                $el.classList.add('border-b-2', 'border-b-clight')
                            }
                            " @dragenter.prevent="
                            if ($el.rowIndex < _dd_src_el.rowIndex) {
                                $el.classList.add('border-t-2', 'border-t-clight')
                            } else if ($el.rowIndex > _dd_src_el.rowIndex) {
                                $el.classList.add('border-b-2', 'border-b-clight')
                            }
                            " @dragleave="_dd_cleanup_styles()" @drop.prevent="
                            _dd_cleanup_styles();
                            move_item('[#item.key#]', _dd_src_el.rowIndex-1, $el.rowIndex-1)">
                    <td class="whitespace-nowrap px-2 py-2">
                        <div class="flex flex-row items-center text-neutral-400">
                            <svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-5 h-5">
                                <g>
                                    <style type="text/css">
                                        .st0 {
                                            fill: none;
                                        }
                                    </style>
                                    <rect x="10" y="6" width="4" height="4"></rect>
                                    <rect x="18" y="6" width="4" height="4"></rect>
                                    <rect x="10" y="14" width="4" height="4"></rect>
                                    <rect x="18" y="14" width="4" height="4"></rect>
                                    <rect x="10" y="22" width="4" height="4"></rect>
                                    <rect x="18" y="22" width="4" height="4"></rect>
                                    <rect id="_Transparent_Rectangle_" class="st0" width="32" height="32"></rect>
                                </g>
                            </svg>
                            <span x-text="img_ix"></span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2">
                        <img :src=`[#base-url#]/img/${img_uuid}/80x80f` class="shadow">
                    </td>
                    <td class="whitespace-nowrap px-4 py-2">
                        <div class="flex flex-row items-center">

                            <button class="[#btn-class-icon#] p-1 text-neutral-600" data-te-toggle="modal"
                                data-te-target="#nb-modal-insert-media" title="[#text Change image#]"
                                @click.prevent="select_image('[#item.key#]',`${img_ix}`)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                </svg>
                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:opacity-60" title="[#text Move up#]"
                                @click.prevent="move_item('[#item.key#]', `${img_ix}`, `${img_ix-1}`)"
                                :disabled="img_ix==0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>
                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:opacity-60" title="[#text Move down#]"
                                @click.prevent="move_item('[#item.key#]', `${img_ix}`, `${img_ix+1}`)"
                                :disabled="(img_ix+1)==form_data['[#item.key#]'].length">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:text-neutral-200"
                                :disabled="!form_data.[#item.key#]" title="[#text Remove#]"
                                @click.prevent="delete_image('[#item.key#]',`${img_ix}`)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
    <button class="[#btn-class-secondary#] flex flex-row items-center mt-4 hover:text-cnormal" style="padding: 5px 10px"
        data-te-toggle="modal" data-te-target="#nb-modal-insert-media"
        @click.prevent="select_image('[#item.key#]',`${form_data.[#item.key#].length}`)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-5 h-5 mr-2 -mt-[1px]">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
        </svg>
        [#text Add image#]
    </button>
</div>