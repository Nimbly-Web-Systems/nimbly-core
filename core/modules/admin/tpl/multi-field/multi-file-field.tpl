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
                <th scope="col" class="px-4 py-2">[#text File#]</th>
                <th scope="col" class="px-4 py-2">[#text Actions#]</th>
            </tr>
        </thead>
        <tbody x-init='form_data.[#item.key#]=[#get record.[#item.key#] empty=[] json#]; get_file_meta(`[#item.key#]`)'
            data-nb-edit-multi-file="[#item.key#]">
            <template x-for="(file_uuid, file_ix) in form_data.[#item.key#]" :key="file_ix">
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
                            <svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"
                                class="w-5 h-5">
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
                            <span x-text="file_ix"></span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-2">
                        <div class="flex flex-row items-center">
                            <template
                                x-if="file_info[file_uuid] && nb.media_library._type(file_info[file_uuid]) == 'doc'">
                                <a :href=`[#base-url#]/download/${file_uuid}` target="_blank"
                                    class="cursor-pointer hover:text-primary" title="[#text Download#]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke-width="1.0" stroke="currentColor" class="w-8 h-8 mr-2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z">
                                        </path>
                                    </svg>
                                </a>
                            </template>
                            <template
                                x-if="file_info[file_uuid] && nb.media_library._type(file_info[file_uuid]) == 'img'">
                                <img :src=`[#base-url#]/img/${file_uuid}/80x60c` class="mr-2">

                            </template>
                            <template
                                x-if="file_info[file_uuid] && nb.media_library._type(file_info[file_uuid]) == 'svg'">
                                <img :src="`[#base_url#]/img/${file_uuid}`"
                                    :class="file_info.aspect_ratio < 1.0? 'max-w-full h-[60px] mr-2' : 'max-h-full w-[80px] mr-2'">
                            </template>
                            <template
                                x-if="file_info[file_uuid] && nb.media_library._type(file_info[file_uuid]) == 'vid'">
                                <video width="80" height="60" controls loading="lazy"
                                    class="h-[60x] w-[80px] mr-2">
                                    <source :src="`[#base-url#]/video/${file_uuid}`">
                                </video>
                            </template>
                            <span x-text='file_info[file_uuid] ? file_info[file_uuid].name : file_uuid'>
                                [#text loading...#]
                            </span>
                        </div>
                        <!--<img :src=`[#base-url#]/img/${file_uuid}/80x80f` class="shadow">-->
                    </td>
                    <td class="whitespace-nowrap px-4 py-2">
                        <div class="flex flex-row items-center">

                            <button class="[#btn-class-icon#] p-1 text-neutral-600" data-te-toggle="modal"
                                data-te-target="#nb-modal-insert-media" title="[#text Change file#]"
                                @click.prevent="select_media('[#item.key#]',`${file_ix}`)">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
                                </svg>

                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:opacity-60" title="[#text Move up#]"
                                @click.prevent="move_item('[#item.key#]', `${file_ix}`, `${file_ix-1}`)"
                                :disabled="file_ix==0">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>
                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:opacity-60" title="[#text Move down#]"
                                @click.prevent="move_item('[#item.key#]', `${file_ix}`, `${file_ix+1}`)"
                                :disabled="(file_ix+1)==form_data['[#item.key#]'].length">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <button class="[#btn-class-icon#] p-1 text-neutral-600
                                disabled:pointer-events-none disabled:text-neutral-200"
                                :disabled="!form_data.[#item.key#]" title="[#text Remove#]"
                                @click.prevent="delete_image('[#item.key#]',`${file_ix}`)">
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
        @click.prevent="select_media('[#item.key#]',`${form_data.[#item.key#].length}`)">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
            class="w-5 h-5 mr-2 -mt-[1px]">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 0 0-1.883 2.542l.857 6a2.25 2.25 0 0 0 2.227 1.932H19.05a2.25 2.25 0 0 0 2.227-1.932l.857-6a2.25 2.25 0 0 0-1.883-2.542m-16.5 0V6A2.25 2.25 0 0 1 6 3.75h3.879a1.5 1.5 0 0 1 1.06.44l2.122 2.12a1.5 1.5 0 0 0 1.06.44H18A2.25 2.25 0 0 1 20.25 9v.776" />
        </svg>

        [#text Add file#]
    </button>
</div>