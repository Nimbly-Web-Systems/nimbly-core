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
                class="flex flex-shrink-0 items-center justify-between rounded-t-md border-b border-neutral-200 px-4 py-2 min-[0px]:rounded-none">

                <!-- Modal title -->
                <div>
                    <h5 class="text-xl font-medium leading-normal text-neutral-800">
                        [text Insert media]
                    </h5>
                    <h6 class="text-sm text-neutral-700">
                        <span x-text="`${first} - ${last} of ${files.length}`"></span>
                        [text files]
                    </h6>

                </div>
                [module admin]
                [media-pagination]

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
                        [media-grid]
                    </div>
                    <div class="flex-none w-[300px] p-2 mx-auto sm:p-4 bg-neutral-200">
                        [media-side-panel]
                    </div>
                </div>
            </div>


            <!-- Modal footer -->
            <div class="mt-auto flex flex-shrink-0 flex-wrap items-center justify-end rounded-b-md p-4 
          border-t border-neutral-200 min-[0px]:rounded-none">
                <button type="button" class="[btn-class-secondary]" data-te-modal-dismiss>
                    [text Cancel]
                </button>
                <button type="button" class="[btn-class-primary] ml-2" :disabled="!file_info" x-show="mode==='insert'"
                    @click="insert_media">
                    [text Insert media]
                </button>
                <button type="button" class="[btn-class-primary] ml-2" :disabled="!file_info" x-show="mode==='select'"
                    @click="set_media">
                    [text Select]
                </button>
            </div>
        </div>
    </div>
</div>


<template id="nb_media_insert_img_landscape_tpl">
    <img class="w-full" style="max-width: {{width}}px; max-height: {{height}}px;" loading="lazy" src="{{src}}"
        srcset="{{srcset}}" sizes="{{sizes}}" height="{{height}}" width="{{width}}">
</template>

<template id="nb_media_insert_img_portrait_tpl">
    <img class="w-full" style="max-width: {{width}}px; max-height: {{height}}px;" loading="lazy" src="{{src}}"
        srcset="{{srcset}}" sizes="{{sizes}}" height="{{height}}" width="{{width}}">
</template>

<template id="nb_media_insert_doc_tpl">
    <a href="[base_url]/download/{{uuid}}" class="cursor-pointer" download="{{name}}"
        title="{{description}}">{{title}}</a>
</template>

<template id="nb_media_insert_vid_tpl">
    <video controls>
        <source src="[base-url]/video/{{uuid}}" type="{{type}}">
        [text Video not supported.]
    </video>
</template>