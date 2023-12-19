<div class="border border-t-0 border-neutral-200 bg-neutral-50 dark:border-neutral-600 dark:bg-neutral-800">
    <h2 class="mb-0" id="heading_youtube">
        <button @click="embed_info.active='youtube'"
            class="group relative flex w-full items-center rounded-none border-0 bg-neutral-50 px-5 py-4 text-left text-base text-neutral-800 transition [overflow-anchor:none] hover:z-[2] focus:z-[3] focus:outline-none dark:bg-neutral-800 dark:text-white [&:not([data-te-collapse-collapsed])]:bg-neutral-50 [&:not([data-te-collapse-collapsed])]:text-primary [&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(229,231,235)] dark:[&:not([data-te-collapse-collapsed])]:bg-neutral-800 dark:[&:not([data-te-collapse-collapsed])]:text-primary-400 dark:[&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(75,85,99)]"
            type="button" data-te-collapse-init data-te-collapse-collapsed data-te-target="#youtube_id"
            aria-expanded="false" aria-controls="youtube_id">

            <svg width="256" height="256" viewBox="0 0 256 256" class="w-5 h-5 mr-2" fill="currentColor">
                <g style="stroke: none; stroke-width: 0; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: none; fill-rule: nonzero; opacity: 1;"
                    transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
                    <path
                        d="M 88.119 23.338 c -1.035 -3.872 -4.085 -6.922 -7.957 -7.957 C 73.144 13.5 45 13.5 45 13.5 s -28.144 0 -35.162 1.881 c -3.872 1.035 -6.922 4.085 -7.957 7.957 C 0 30.356 0 45 0 45 s 0 14.644 1.881 21.662 c 1.035 3.872 4.085 6.922 7.957 7.957 C 16.856 76.5 45 76.5 45 76.5 s 28.144 0 35.162 -1.881 c 3.872 -1.035 6.922 -4.085 7.957 -7.957 C 90 59.644 90 45 90 45 S 90 30.356 88.119 23.338 z M 36 58.5 v -27 L 59.382 45 L 36 58.5 z"
                        style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: currentColor; fill-rule: nonzero; opacity: 1;"
                        transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round" />
                </g>
            </svg>

            [#text Embed Youtube#]

            <span
                class="-mr-1 ml-auto h-5 w-5 shrink-0 rotate-[-180deg] fill-[#336dec] transition-transform duration-200 ease-in-out group-[[data-te-collapse-collapsed]]:mr-0 group-[[data-te-collapse-collapsed]]:rotate-0 group-[[data-te-collapse-collapsed]]:fill-[#212529] motion-reduce:transition-none dark:fill-blue-300 dark:group-[[data-te-collapse-collapsed]]:fill-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </span>
        </button>
    </h2>
    <div id="youtube_id" class="!visible hidden" data-te-collapse-item aria-labelledby="heading_youtube"
        data-te-parent="#media_modal_embed_options">
        <div class="px-5 py-4">

            <div class="relative max-w-xs" data-te-input-wrapper-init>
                <input type="text" value="" name="youtube_id" placeholder="" @keyup="embed_info.active='youtube'"
                    x-model="embed_info.youtube.id" class="
                  peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                  leading-[2.15] outline-none transition-all duration-200 ease-linear 
                  focus:placeholder:opacity-100 
                  motion-reduce:transition-none
                  data-[te-input-state-active]:placeholder:opacity-100 
                  [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" />
                <label for="youtube_id" class="pointer-events-none absolute left-3 top-0 mb-0 
                  max-w-[90%] origin-[0_0] truncate pt-[0.33rem] leading-[2.15] 
                  text-neutral-600 transition-all duration-200 ease-out 
                  peer-focus:-translate-y-[1.15rem] 
                  peer-focus:scale-[0.8] peer-focus:text-primary 
                  peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                  peer-data-[te-input-state-active]:scale-[0.8] 
                  motion-reduce:transition-none">
                    [#text Youtube ID#]
                </label>
            </div>
            <div class="flex flex-row items-center gap-4 my-4 flex-wrap min-h-[40px]">

                <div class="text-neutral-600 ">[#text Video size:#]</div>

                <div class="flex items-center gap-2">
                    <div class="relative" data-te-input-wrapper-init>
                        <input type="number" x-model="embed_info.youtube.width"
                            class="peer block min-h-[auto] w-full max-w-[80px] rounded border-0 
              bg-transparent px-3 py-[0.32rem] leading-[1.6] outline-none transition-all duration-200 
              ease-linear focus:placeholder:opacity-100 peer-focus:text-primary data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:peer-focus:text-primary [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
                            id="youtube_width" placeholder="[#text Width#]" />
                        <label for="youtube_width"
                            class="bg-neutral-50 pointer-events-none absolute z-10 left-3 top-0 mb-0 max-w-[90%] origin-[0_0] truncate pt-[0.37rem] leading-[1.6] text-neutral-500 transition-all duration-200 ease-out peer-focus:-translate-y-[0.9rem] peer-focus:scale-[0.8] peer-focus:text-primary peer-data-[te-input-state-active]:-translate-y-[0.9rem] peer-data-[te-input-state-active]:scale-[0.8] motion-reduce:transition-none dark:text-neutral-200 dark:peer-focus:text-primary">
                            [#text Width#]
                        </label>
                    </div>
                    x
                    <div class="relative" data-te-input-wrapper-init>
                        <input type="number" x-model="embed_info.youtube.height"
                            class="peer block min-h-[auto] w-full max-w-[80px] rounded border-0 bg-transparent px-3 py-[0.32rem] leading-[1.6] outline-none transition-all duration-200 ease-linear focus:placeholder:opacity-100 peer-focus:text-primary data-[te-input-state-active]:placeholder:opacity-100 motion-reduce:transition-none dark:text-neutral-200 dark:placeholder:text-neutral-200 dark:peer-focus:text-primary [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0"
                            id="youtube_height" placeholder="[#text Height#]" />
                        <label for="youtube_height"
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

                <template x-if="embed_info.active=='youtube' 
                    && embed_info.youtube.id && embed_info.youtube.id.length>10">
                    <div data-nb-embed="youtube">
                        <div>
                            <iframe 
                                :width="embed_info.youtube.width" 
                                :height="embed_info.youtube.height" 
                                :src="`https://www.youtube.com/embed/${embed_info.youtube.id}`">
                            </iframe>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>