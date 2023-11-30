<!-- Insert media modal -->
<div data-te-modal-init x-data="media_insert()"
  class="fixed left-0 top-0 z-[1055] hidden h-full w-full overflow-y-auto overflow-x-hidden outline-none p-2 sm:p-4"
  id="nb-modal-insert-media" tabindex="-1" aria-labelledby="nb-modal-insert-media" aria-hidden="true">
  <div data-te-modal-dialog-ref
    class="pointer-events-none relative w-auto translate-y-[-50px] opacity-0 transition-all duration-300 ease-in-out min-[0px]:m-0 min-[0px]:h-full min-[0px]:max-w-none">
    <div
      class="pointer-events-auto relative flex w-full flex-col rounded-md bg-neutral-50
       bg-clip-padding text-current shadow-lg outline-none dark:bg-neutral-600 min-[0px]:h-full min-[0px]:rounded-none min-[0px]:border-0">
      <div
        class="flex flex-shrink-0 items-center justify-between rounded-t-md border-b border-neutral-200 p-4 min-[0px]:rounded-none">
        <!-- Modal title -->
        <h5 class="text-xl font-medium leading-normal text-neutral-800 dark:text-neutral-200"
          id="exampleModalFullscreenLabel">
          [text Insert media]
        </h5>
        <!-- Close button -->
        <button type="button"
          class="box-content rounded-none border-none hover:no-underline hover:opacity-75 focus:opacity-100 focus:shadow-none focus:outline-none"
          data-te-modal-dismiss aria-label="Close">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
            stroke="currentColor" class="h-6 w-6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Modal body -->
      <div class="relative min-[0px]:overflow-y-auto">
        <div class="flex flex-wrap flex-col-reverse sm:flex-row sm:flex-nowrap">

          <div class="grow p-4 md:p-6 lg:p-8 bg-neutral-100">

            <div
              class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 md:gap-6 lg:gap-8">
              [data .files_meta filter=type:image/jpeg||image/png||image/gif sort=_modified|num|desc]
              [repeat data.files_meta tpl=grid-img var=img]
            </div>

          </div>
          <div class="flex-none w-[300px] p-2 mx-auto sm:p-4 bg-neutral-200">

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
                  <p>[text Click to upload file]</p>
                </div>
              </div>
            </div>

            <div class="border border-neutral-200 rounded my-4 p-4 bg-neutral-100" id="nb_file_info"
              @nb_upload_ready.document="handle_upload_ready" x-cloak x-show="file_info">
              <template x-if="file_info">
                <div>
                  <figure class="h-[230px] w-[230px] mx-auto flex items-center justify-center bg-neutral-50 shadow-md">
                    <img :src="`[base_url]/img/${file_info.uuid}/230x230f`" :width="file_info.width"
                      :height="file_info.height"
                      :class="file_info.aspect_ratio < 1.0? 'max-h-full w-auto' : 'max-w-full h-auto'">
                  </figure>
                  <p class="text-xs font-bold mt-2" x-text="file_info.name"></p>
                  <p class="text-xs mb-2 flex justify-between text-neutral-400">
                    <span x-text="`${file_info.width}x${file_info.height}`">---</span>
                    <span x-text="file_info.size.fileSize(1) || ''">---</span>
                  </p>
                  <button @click="confirm('[text Delete permanently? Are you sure?]') && delete_file(file_info.uuid)"
                    class="inline-block cursor-pointer rounded border border-neutral-400
                     hover:border-cnormal px-1 pb-0.5 pt-1
                     text-xs font-medium uppercase text-neutral-700 transition duration-150 ease-in-out
                      hover:bg-neutral-50 focus:bg-neutral-100 focus:outline-none focus:ring-0 active:bg-clight">
                    [text Delete]
                  </button>

                  <!-- title input -->
                  <div class="relative mt-6 mb-4" data-te-input-wrapper-init>
                    <input type="text" x-model="file_info.title"
                      class="peer block min-h-[auto] w-full rounded border-0 bg-neutral-50 
                        px-3 py-[0.33rem] text-xs leading-[1.5] outline-none transition-all"
                      id="nb_media_title" placeholder="" />
                    <label for="nb_media_title"
                      class="pointer-events-none absolute left-3 top-0 mb-0 max-w-[90%] origin-[0_0] 
                      truncate pt-[0.37rem] text-xs leading-[1.5] text-neutral-500 
                       -translate-y-[0.75rem] scale-[0.8] ">
                      [text Title]
                    </label>
                  </div>

                  <!-- description input -->
                  <div class="relative" data-te-input-wrapper-init>
                    <textarea x-model="file_info.description" 
                      class="peer block min-h-[auto] w-full rounded border-0 bg-neutral-50 
                        text-xs
                        px-3 py-[0.32rem] leading-[1.6] 
                        outline-none transition-all duration-200 ease-linear 
                        "
                      id="nb_media_description"
                      rows="3"
                      placeholder=""></textarea>
                    <label
                      for="nb_media_description"
                      class="text-xs pointer-events-none absolute left-3 top-0 
                      mb-0 max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[1.6]
                       text-neutral-500 
                       -translate-y-[0.75rem] scale-[0.8]"
                      >
                      [text Description]
                      </label
                    >
                  </div>
                </div>



              </template>
            </div>
          </div>
        </div>


      </div>

      <!-- Modal footer -->
      <div class="mt-auto flex flex-shrink-0 flex-wrap items-center justify-end rounded-b-md p-4 
          border-t border-neutral-200 min-[0px]:rounded-none">
        <button type="button" class="[btn-class-secondary]" data-te-modal-dismiss>
          [text Cancel]
        </button>
        <button type="button" class="[btn-class-primary] ml-2" :disabled="!file_info" @click="insert_media">
          [text Insert media]
        </button>
      </div>
    </div>
  </div>
</div>

<template id="nb_media_insert_img_tpl">
  <figure>
    <img class="w-full object-scale-down" loading="lazy" src="{{src}}" srcset="{{srcset}}" sizes="{{sizes}}"
      height="{{height}}" width="{{width}}">
  </figure>
</template>