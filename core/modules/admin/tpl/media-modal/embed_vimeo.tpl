<div class="rounded-t-lg border border-neutral-200 bg-neutral-50 text-neutral-600">
    <h2 class="mb-0" id="heading_vimeo">
        <button @click="embed_info.active='vimeo'"
            class="group relative flex w-full items-center rounded-t-[15px] border-0 bg-neutral-50 px-5 py-4 text-left text-base text-neutral-800 transition [overflow-anchor:none] hover:z-[2] focus:z-[3] focus:outline-none dark:bg-neutral-800 dark:text-white [&:not([data-te-collapse-collapsed])]:bg-neutral-50 [&:not([data-te-collapse-collapsed])]:text-primary [&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(229,231,235)] dark:[&:not([data-te-collapse-collapsed])]:bg-neutral-800 dark:[&:not([data-te-collapse-collapsed])]:text-primary-400 dark:[&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(75,85,99)]"
            type="button" data-te-collapse-init data-te-target="#vimeo_id" aria-expanded="true"
            aria-controls="vimeo_id">


            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" class="w-4 h-4 mr-2"
                fill="currentColor">
                <path
                    d="M22.875 10.063c-2.442 5.217-8.337 12.319-12.063 12.319-3.672 0-4.203-7.831-6.208-13.043-.987-2.565-1.624-1.976-3.474-.681l-1.128-1.455c2.698-2.372 5.398-5.127 7.057-5.28 1.868-.179 3.018 1.098 3.448 3.832.568 3.593 1.362 9.17 2.748 9.17 1.08 0 3.741-4.424 3.878-6.006.243-2.316-1.703-2.386-3.392-1.663 2.673-8.754 13.793-7.142 9.134 2.807z" />
            </svg>

            [#text Embed Vimeo#]

            <span
                class="ml-auto h-5 w-5 shrink-0 rotate-[-180deg] fill-[#336dec] transition-transform duration-200 ease-in-out group-[[data-te-collapse-collapsed]]:rotate-0 group-[[data-te-collapse-collapsed]]:fill-[#212529] motion-reduce:transition-none dark:fill-blue-300 dark:group-[[data-te-collapse-collapsed]]:fill-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </span>
        </button>
    </h2>
    <div id="vimeo_id" class="!visible" data-te-collapse-item data-te-collapse-show aria-labelledby="heading_vimeo"
        data-te-parent="#media_modal_embed_options">
        <div class="px-5 py-4">
            <p class="text-sm mb-4 max-w-[75ch] text-neutral-500">[#text vimeo_embed_help#]</p>
            <div class="flex flex-row items-center flex-wrap gap-2">
                <div class="relative max-w-xs " data-te-input-wrapper-init>
                    <input type="text" value="" name="vimeo_id" placeholder="" x-model="embed_info.vimeo.id"
                        @keyup="embed_info.active='vimeo'" class="
                  peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                  leading-[2.15] outline-none transition-all duration-200 ease-linear 
                  focus:placeholder:opacity-100 
                  motion-reduce:transition-none
                  data-[te-input-state-active]:placeholder:opacity-100 
                  [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" />
                    <label for="vimeo_id" class="pointer-events-none absolute left-3 top-0 mb-0 
                  max-w-[90%] origin-[0_0] truncate pt-[0.33rem] leading-[2.15] 
                  text-neutral-600 transition-all duration-200 ease-out 
                  peer-focus:-translate-y-[1.15rem] 
                  peer-focus:scale-[0.8] peer-focus:text-primary 
                  peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                  peer-data-[te-input-state-active]:scale-[0.8] 
                  motion-reduce:transition-none">
                        [#text Vimeo ID#]
                    </label>
                </div>
                <div class="relative max-w-xs " data-te-input-wrapper-init>
                    <input type="text" value="" name="vimeo_hash" placeholder="" x-model="embed_info.vimeo.hash" class="
                    peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                    leading-[2.15] outline-none transition-all duration-200 ease-linear 
                    focus:placeholder:opacity-100 
                    motion-reduce:transition-none
                    data-[te-input-state-active]:placeholder:opacity-100 
                    [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" />
                    <label for="vimeo_hash" class="pointer-events-none absolute left-3 top-0 mb-0 
                    max-w-[90%] origin-[0_0] truncate pt-[0.33rem] leading-[2.15] 
                    text-neutral-600 transition-all duration-200 ease-out 
                    peer-focus:-translate-y-[1.15rem] 
                    peer-focus:scale-[0.8] peer-focus:text-primary 
                    peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                    peer-data-[te-input-state-active]:scale-[0.8] 
                    motion-reduce:transition-none">
                        [#text Vimeo Hash#]
                    </label>
                </div>
            </div>

            <div class="flex flex-row items-center gap-4 my-4 flex-wrap min-h-[40px]">

                <div class="text-neutral-600 ">[#text Video size:#]</div>

                <div class="mb-[0.125rem] block min-h-[1.5rem] pl-[1.5rem]">
                    <input x-model="embed_info.vimeo.mode" value="responsive" class="relative float-left -ml-[1.5rem] mr-1 mt-0.5 h-5 w-5 
              appearance-none rounded-full border-2 border-solid border-neutral-300 
              before:pointer-events-none before:absolute before:h-4 before:w-4 before:scale-0 
              before:rounded-full before:bg-transparent before:opacity-0 before:shadow-[0px_0px_0px_13px_transparent] 
              before:content-[''] after:absolute after:z-[1] after:block after:h-4 after:w-4 after:rounded-full
              after:content-[''] checked:border-primary checked:before:opacity-[0.16] 
              checked:after:absolute checked:after:left-1/2 checked:after:top-1/2 checked:after:h-[0.625rem] 
              checked:after:w-[0.625rem] checked:after:rounded-full
               checked:after:border-primary checked:after:bg-primary 
               checked:after:content-[''] checked:after:[transform:translate(-50%,-50%)] 
               hover:cursor-pointer hover:before:opacity-[0.04] hover:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)]
                focus:shadow-none focus:outline-none focus:ring-0 focus:before:scale-100 focus:before:opacity-[0.12] 
                focus:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)] 
                focus:before:transition-[box-shadow_0.2s,transform_0.2s]
                 checked:focus:border-primary checked:focus:before:scale-100 
                 checked:focus:before:shadow-[0px_0px_0px_13px_#3b71ca] 
                 checked:focus:before:transition-[box-shadow_0.2s,transform_0.2s]" checked type="radio"
                        name="vimeo_embed_mode" id="vimeo_embed_mode1" />
                    <label class="mt-px inline-block pl-[0.15rem] hover:cursor-pointer" for="vimeo_embed_mode1">
                        [#text Responsive#]
                    </label>
                </div>
                <div class="mb-[0.125rem] block min-h-[1.5rem] pl-[1.5rem]">




                    <input value="fixed" x-model="embed_info.vimeo.mode" class="relative float-left -ml-[1.5rem] mr-1 mt-0.5 h-5 w-5 
              appearance-none rounded-full border-2 border-solid border-neutral-300 
              before:pointer-events-none before:absolute before:h-4 before:w-4 before:scale-0 
              before:rounded-full before:bg-transparent before:opacity-0 before:shadow-[0px_0px_0px_13px_transparent] 
              before:content-[''] after:absolute after:z-[1] after:block after:h-4 after:w-4 after:rounded-full
              after:content-[''] checked:border-primary checked:before:opacity-[0.16] 
              checked:after:absolute checked:after:left-1/2 checked:after:top-1/2 checked:after:h-[0.625rem] 
              checked:after:w-[0.625rem] checked:after:rounded-full
               checked:after:border-primary checked:after:bg-primary 
               checked:after:content-[''] checked:after:[transform:translate(-50%,-50%)] 
               hover:cursor-pointer hover:before:opacity-[0.04] hover:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)]
                focus:shadow-none focus:outline-none focus:ring-0 focus:before:scale-100 focus:before:opacity-[0.12] 
                focus:before:shadow-[0px_0px_0px_13px_rgba(0,0,0,0.6)] 
                focus:before:transition-[box-shadow_0.2s,transform_0.2s]
                 checked:focus:border-primary checked:focus:before:scale-100 
                 checked:focus:before:shadow-[0px_0px_0px_13px_#3b71ca] 
                 checked:focus:before:transition-[box-shadow_0.2s,transform_0.2s]" type="radio" name="vimeo_embed_mode"
                        id="vimeo_embed_mode2" />

                    <label class="mt-px inline-block pl-[0.15rem] hover:cursor-pointer" for="vimeo_embed_mode2">
                        [#text Fixed size#]
                    </label>
                </div>
                <div class="flex items-center gap-2" x-show="embed_info.vimeo.mode=='fixed'">
                    <div class="relative" data-te-input-wrapper-init>
                        <input type="number" x-model="embed_info.vimeo.width"
                            class="peer block min-h-[auto] w-full max-w-[80px] rounded border-0 
                  bg-transparent px-3 py-[0.32rem] leading-[1.6] outline-none transition-all duration-200 
                  ease-linear focus:placeholder:opacity-100 peer-focus:text-primary data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:peer-focus:text-primary [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
                            id="vimeo_width" placeholder="[#text Width#]" />
                        <label for="vimeo_width"
                            class="bg-neutral-50 pointer-events-none absolute z-10 left-3 top-0 mb-0 max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[1.6] text-neutral-500 transition-all duration-200 ease-out peer-focus:-translate-y-[0.9rem] peer-focus:scale-[0.8] peer-focus:text-primary peer-data-[te-input-state-active]:-translate-y-[0.9rem] peer-data-[te-input-state-active]:scale-[0.8] motion-reduce:transition-none dark:text-neutral-200 dark:peer-focus:text-primary">
                            [#text Width#]
                        </label>
                    </div>
                    x
                    <div class="relative" data-te-input-wrapper-init>
                        <input type="number" x-model="embed_info.vimeo.height"
                            class="peer block min-h-[auto] w-full max-w-[80px] rounded border-0 bg-transparent px-3 py-[0.32rem] leading-[1.6] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 peer-focus:text-primary data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:peer-focus:text-primary [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
                            id="vimeo_height" placeholder="[#text Height#]" />
                        <label for="vimeo_height"
                            class="bg-neutral-50 pointer-events-none absolute z-10 left-3 top-0 mb-0 max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[1.6] text-neutral-500 transition-all duration-200 ease-out peer-focus:-translate-y-[0.9rem] peer-focus:scale-[0.8] peer-focus:text-primary peer-data-[te-input-state-active]:-translate-y-[0.9rem] peer-data-[te-input-state-active]:scale-[0.8] motion-reduce:transition-none dark:text-neutral-200 dark:peer-focus:text-primary">
                            [#text Height#]
                        </label>
                    </div>
                    px
                </div>
            </div>

            <div class="rounded p-4 mt-6 relative border border-neutral-300 min-h-[100px]">
                <h3 class="absolute -top-[9px] left-[6px] text-xs text-neutral-400 bg-neutral-50 px-1">
                    [#text Preview#]
                </h3>

                <template x-if="embed_info.active=='vimeo' 
            && embed_info.vimeo.mode=='responsive' 
            && embed_info.vimeo.id && embed_info.vimeo.id.length>8">
                    <div data-nb-embed="vimeo">
                        <div style="padding:55% 0 0 0;position:relative;">
                            <iframe
                                :src="`https://player.vimeo.com/video/${embed_info.vimeo.id}?${embed_info.vimeo.hash? 'h=' + embed_info.vimeo.hash + '&' : ''}badge=0&amp;autopause=0&amp;player_id=0&amp;app_id=58479`"
                                frameborder="0" allow="autoplay; fullscreen; picture-in-picture"
                                style="position:absolute;top:0;left:0;width:100%;height:100%;">
                            </iframe>
                            <script src="https://player.vimeo.com/api/player.js"></script>
                        </div>
                    </div>
                </template>

                <template x-if="embed_info.active=='vimeo' 
            && embed_info.vimeo.mode=='fixed' 
            && embed_info.vimeo.id && embed_info.vimeo.id.length>8">
                    <div data-nb-embed="vimeo">
                        <div>
                            <iframe
                                :src="`https://player.vimeo.com/video/${embed_info.vimeo.id}?${embed_info.vimeo.hash? 'h=' + embed_info.vimeo.hash + '&' : ''}badge=0&amp;autopause=0&amp;player_id=0&amp;app_id=58479`"
                                width="640" height="360" :width="embed_info.vimeo.width"
                                :height="embed_info.vimeo.height" frameborder="0"
                                allow="autoplay; fullscreen; picture-in-picture">
                            </iframe>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>