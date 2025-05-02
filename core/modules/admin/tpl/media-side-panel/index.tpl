<div class="border border-dashed border-neutral-400 relative bg-neutral-50 h-[80px] text-neutral-600">

    <input class="cursor-pointer relative block opacity-0 w-full h-full z-50" type="file" id="nb-edit-upload"
        data-nb-upload="nb-insert-media" />

    <div class="top-0 right-0 left-0 bottom-0 absolute">
        <div class="flex flex-col items-center justify-center h-full">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            <p>[#text Click to upload file#]</p>
            <p class="text-xs text-neutral-500">[#text Max file size:#] [#fmt [#max-upload-size#] bytes#]</p>
        </div>
    </div>
</div>

<div class="border border-neutral-200 rounded my-4 p-4 bg-neutral-100" id="nb_file_info"
    @nb_upload_ready.document="handle_upload_ready" x-cloak x-show="file_info">
    <template x-if="file_info">
        <div>
            <template x-if="file_type() === 'img'">
                <figure class="h-[230px] w-[230px] mx-auto flex items-center justify-center bg-neutral-50 shadow-md">
                    <img :src="`[#base_url#]/img/${file_info.uuid}/230x230f`" :width="file_info.width"
                        :height="file_info.height" loading="lazy"
                        :class="file_info.aspect_ratio < 1.0? 'max-h-full w-auto' : 'max-w-full h-auto'">
                </figure>
            </template>

            <template x-if="file_type() === 'svg'">
                <figure class="h-[230px] w-[230px] mx-auto flex items-center justify-center bg-neutral-50 shadow-md">
                    <img :src="`[#base_url#]/img/${file_info.uuid}`"
                        :class="file_info.aspect_ratio < 1.0? 'max-h-full w-auto' : 'max-w-full h-auto'">
                </figure>
            </template>

            <template x-if="file_type() === 'vid'">
                <video width="230" height="230" controls loading="lazy"
                    class="h-[230px] w-[230px] mx-auto flex items-center justify-center bg-neutral-50 shadow-md">
                    <source :src="`[#base-url#]/video/${file_info.uuid}`" :type="`video/${vid_type()}`">
                </video>
            </template>

            <template x-if="file_type() === 'audio'">
                <audio controls width="230" class="w-[130px] mx-auto">
                    <source :src="`[#base-url#]/download/${file_info.uuid}`" :type="`audio/${audio_type()}`">
                </audio>
            </template>

            <p class="text-xs font-bold mt-2 break-words" x-text="file_info.name"></p>
            <div
                class="text-xs flex items-center flex-wrap justify-between text-neutral-600 mt-0.5 mb-4 gap-x-1 gap-y-0.5">
                <span class="text-xs text-neutral-600" x-text="file_date(file_info._created)">---</span>
                <template x-if="file_info.width">
                    <span x-text="`${file_info.width}x${file_info.height}`">---</span>
                </template>
                <span x-text="file_info.size? file_info.size.fileSize(1) : ''">---</span>
                <template x-if="file_info.in_use===false">
                    <div
                        class="flex flex-row items-center font-bold text-yellow-900 bg-yellow-400 border border-yellow-700 rounded py-1 px-2">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                            class="w-4 h-4 mr-1">
                            <title>[#text File not in use#]</title>
                            <path fill-rule="evenodd"
                                d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
                                clip-rule="evenodd" />
                        </svg>
                        [#text File not in use#]
                    </div>
                </template>
            </div>
            <a :href="`[#base-url#]/download/${file_info.uuid}`" :download="file_info.name" class="inline-block cursor-pointer rounded border border-neutral-400
          hover:border-cnormal px-1 pb-0.5 pt-1
          text-xs font-medium uppercase text-neutral-700 transition duration-150 ease-in-out
           hover:bg-neutral-50 focus:bg-neutral-100 focus:outline-none focus:ring-0 active:bg-clight">Download</a>

            <button @click="confirm('[#text Delete permanently? Are you sure?#]') && delete_file(file_info.uuid)" class="inline-block cursor-pointer rounded border border-neutral-400
           hover:border-cnormal px-1 pb-0.5 pt-1
           text-xs font-medium uppercase text-neutral-700 transition duration-150 ease-in-out
            hover:bg-neutral-50 focus:bg-neutral-100 focus:outline-none focus:ring-0 active:bg-clight">
                [#text Delete#]
            </button>

            <!-- title input -->
            <div class="relative mt-6 mb-4" data-te-input-wrapper-init>
                <input type="text" x-model="file_info.title" class="peer block min-h-[auto] w-full rounded border-0 bg-neutral-50 
              px-3 py-[0.33rem] text-xs leading-[1.5] outline-none transition-all" id="nb_media_title"
                    placeholder="" />
                <label for="nb_media_title" class="pointer-events-none absolute left-3 top-0 mb-0 max-w-[90%] origin-[0_0] 
            truncate pt-[0.37rem] text-xs leading-[1.5] text-neutral-500 
             -translate-y-[0.75rem] scale-[0.8] ">
                    [#text Title#]
                </label>
            </div>

            <!-- description input -->
            <div class="relative" data-te-input-wrapper-init>
                <textarea x-model="file_info.description" class="peer block min-h-[auto] w-full rounded border-0 bg-neutral-50 
              text-xs
              px-3 py-[0.32rem] leading-[1.6] 
              outline-none transition-all duration-200 ease-linear 
              " id="nb_media_description" rows="3" placeholder=""></textarea>
                <label for="nb_media_description" class="text-xs pointer-events-none absolute left-3 top-0 
            mb-0 max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[1.6]
             text-neutral-500 
             -translate-y-[0.75rem] scale-[0.8]">
                    [#text Description#]
                </label>
            </div>

            <button class="[#btn-class-primary#] my-4" @click="save_media"
                x-show="typeof hide_save_button === 'undefined'">Save</button>

            <!-- document template picker -->
            <template x-if="file_type() === 'doc'">
                <div class="text-xs my-8 text-neutral-700">

                    <fieldset class="border border-neutral-200 px-4 py-2">
                        <legend class="text-xs text-neutral-600">[#text Select link type#]</legend>

                        <div class="flex flex-row items-center">
                            <input type="radio" id="link" name="link_type" value="link" checked
                                x-model="embed_info.doc.insert_mode" />
                            <label for="link" class="text-neutral-600 ml-2 pt-[1px]">Link</label>
                        </div>

                        <div class="mt-2 flex flex-row items-center">
                            <input type="radio" id="download" name="link_type" value="download"
                                x-model="embed_info.doc.insert_mode" />
                            <label for="download" class="text-neutral-600 ml-2 pt-[1px]">Download</label>
                        </div>

                    </fieldset>

                </div>
            </template>
        </div>
    </template>
</div>