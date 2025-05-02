<!-- Insert media modal -->
<div data-te-modal-init x-data="media_insert()"
    class="fixed left-0 top-0 z-[1055] hidden h-full w-full overflow-y-auto overflow-x-hidden outline-none p-2 sm:p-4"
    id="nb-modal-insert-media" tabindex="-1" aria-labelledby="nb-modal-insert-media" aria-hidden="true">
    <div data-te-modal-dialog-ref
        class="pointer-events-none relative w-auto translate-y-[-50px] opacity-0 transition-all duration-300 ease-in-out min-[0px]:m-0 min-[0px]:h-full min-[0px]:max-w-none">
        <div
            class="pointer-events-auto relative flex w-full flex-col rounded-md bg-neutral-50
       bg-clip-padding text-current shadow-lg outline-none min-[0px]:h-full min-[0px]:rounded-none min-[0px]:border-0">
            <div
                class="flex flex-shrink-0 items-center justify-between rounded-t-md border-b border-neutral-200 px-4 py-2 min-[0px]:rounded-none">

                <!-- Modal title -->
                <div>
                    <h5 class="text-xl font-medium leading-normal text-neutral-800">
                        [#text Insert media#]
                    </h5>
                    <h6 class="text-sm text-neutral-700">
                        <span x-text="`${first} - ${last} of ${files.length}`"></span>
                        [#text files#] <span x-text="mode"></span>
                    </h6>

                </div>
                [#module admin#]
                [#media-pagination#]

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

                <div class="flex">
                    <!-- tab buttons -->
                    <ul class="flex list-none flex-col flex-wrap pl-0 w-12" role="tablist" data-te-nav-ref>
                        <li role="presentation" class="text-center w-12">
                            <a href="#tab_media_library" id='tab_media_library_btn' class="w-12 my-2 block border-x-0 border-b-2 border-t-0 border-transparent px-3 pb-3.5 
                                    pt-4 text-xs font-medium uppercase leading-tight text-neutral-500 
                                    hover:isolate hover:border-transparent
                                     hover:bg-neutral-100 focus:isolate 
                                     focus:border-transparent data-[te-nav-active]:border-primary
                                      data-[te-nav-active]:text-primary
                                      data-[te-nav-active]:bg-neutral-100" data-te-toggle="pill"
                                title="[#text Pick from media library#]" data-te-target="#tab_media_library"
                                @click="if (mode != 'select') mode='insert'" data-te-nav-active role="tab"
                                aria-controls="tab_media_library" aria-selected="true">

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                                </svg>
                            </a>
                        </li>
                        <li role="presentation" class="w-12 text-center">
                            <a href="#tab_media_embed" :class="mode==='select' && 'opacity-50 pointer-events-none'"
                                @click="if (mode != 'select') mode='embed'" title="[#text Embed external media#]" class="w-12 my-2 block border-x-0 border-b-2 border-t-0 border-transparent 
                                px-3 pb-3.5 pt-4 text-xs font-medium uppercase leading-tight
                                 text-neutral-500 
                                 hover:isolate hover:border-transparent hover:bg-neutral-100
                                 focus:isolate focus:border-transparent
                                  data-[te-nav-active]:border-primary
                                 data-[te-nav-active]:bg-neutral-100
                                  data-[te-nav-active]:text-primary" data-te-toggle="pill"
                                data-te-target="#tab_media_embed" role="tab" aria-controls="tab_media_embed"
                                aria-selected="false">

                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>

                            </a>
                        </li>
                    </ul>
                    <div class="w-full bg-neutral-100">
                        <div class="hidden opacity-100 transition-opacity duration-150 ease-linear data-[te-tab-active]:block"
                            id="tab_media_library" role="tabpanel" aria-labelledby="tab_media_library"
                            data-te-tab-active>

                            <div class="flex flex-wrap flex-col-reverse sm:flex-row sm:flex-nowrap">

                                <div class="grow p-4 md:p-6 lg:p-8">
                                    [#media-grid#]
                                </div>
                                <div class="flex-none w-[300px] p-2 mx-auto sm:p-4 bg-neutral-200">
                                    [#media-side-panel#]
                                </div>
                            </div>
                        </div>

                        <div class="hidden opacity-100 transition-opacity duration-150 ease-linear data-[te-tab-active]:block"
                            id="tab_media_embed" role="tabpanel" aria-labelledby="tab_media_embed">

                            <div class="w-full p-4 md:p-6 lg:p-8">
                                <div id="media_modal_embed_options">
                                    [#embed_vimeo#]
                                    [#embed_youtube#]
                                    [#embed_img#]
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Modal footer -->
            <div class="mt-auto flex flex-shrink-0 flex-wrap items-center justify-end rounded-b-md p-4 
          border-t border-neutral-200 min-[0px]:rounded-none">

                <button type="button" class="[#btn-class-secondary#] ml-2" data-te-modal-dismiss>
                    [#text Cancel#]
                </button>
                <button type="button" class="[#btn-class-primary#] ml-2" :disabled="!file_info" x-show="mode==='insert'"
                    @click="insert_media">
                    [#text Insert media#]
                </button>
                <button type="button" class="[#btn-class-primary#] ml-2" :disabled="!file_info" x-show="mode==='select'"
                    @click="set_media">
                    [#text Select#]
                </button>
                <button type="button" class="[#btn-class-primary#] ml-2" :disabled="!can_embed()"
                    x-show="mode==='embed'" @click="embed_media">
                    [#text Embed media#]
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

<template id="nb_media_insert_svg_tpl">
    <img class="w-full" src="{{src}}">
</template>

<template id="nb_media_insert_doc_download_tpl">
    <a href="[#base_url#]/download/{{uuid}}" class="cursor-pointer" download="{{name}}"
        title="{{description}}">{{title}}</a>
</template>

<template id="nb_media_insert_doc_link_tpl">
    <a href="[#base_url#]/download/{{uuid}}" class="cursor-pointer" target="_blank"
        title="{{description}}">{{title}}</a>
</template>

<template id="nb_media_insert_vid_tpl">
    <video controls>
        <source src="[#base-url#]/video/{{uuid}}" type="{{type}}">
        [#text Video not supported.#]
    </video>
</template>