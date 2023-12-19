<!-- https://picsum.photos/200/300 -->

<div class="border border-t-0 border-neutral-200 bg-neutral-50 dark:border-neutral-600 dark:bg-neutral-800">
    <h2 class="mb-0" id="heading_extimg">
        <button @click="embed_info.active='extimg'"
            class="group relative flex w-full items-center rounded-none border-0 bg-neutral-50 px-5 py-4 text-left text-base text-neutral-800 transition [overflow-anchor:none] hover:z-[2] focus:z-[3] focus:outline-none dark:bg-neutral-800 dark:text-white [&:not([data-te-collapse-collapsed])]:bg-neutral-50 [&:not([data-te-collapse-collapsed])]:text-primary [&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(229,231,235)] dark:[&:not([data-te-collapse-collapsed])]:bg-neutral-800 dark:[&:not([data-te-collapse-collapsed])]:text-primary-400 dark:[&:not([data-te-collapse-collapsed])]:[box-shadow:inset_0_-1px_0_rgba(75,85,99)]"
            type="button" data-te-collapse-init data-te-collapse-collapsed data-te-target="#extimg_id"
            aria-expanded="false" aria-controls="extimg_id">

            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" data-slot="icon"
                class="w-5 h-5 mr-2">
                <path fill-rule="evenodd"
                    d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z"
                    clip-rule="evenodd" />
            </svg>

            [#text Embed external image#]

            <span
                class="-mr-1 ml-auto h-5 w-5 shrink-0 rotate-[-180deg] fill-[#336dec] transition-transform duration-200 ease-in-out group-[[data-te-collapse-collapsed]]:mr-0 group-[[data-te-collapse-collapsed]]:rotate-0 group-[[data-te-collapse-collapsed]]:fill-[#212529] motion-reduce:transition-none dark:fill-blue-300 dark:group-[[data-te-collapse-collapsed]]:fill-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                </svg>
            </span>
        </button>
    </h2>
    <div id="extimg_id" class="!visible hidden" data-te-collapse-item aria-labelledby="heading_extimg"
        data-te-parent="#media_modal_embed_options">
        <div class="px-5 py-4">

            <div class="relative max-w-xs" data-te-input-wrapper-init>
                <input type="text" value="" name="extimg_id" placeholder="" @keyup="embed_info.active='extimg'"
                    x-model="embed_info.extimg.url" class="
                  peer block min-h-[auto] w-full rounded border-0 bg-transparent px-2 py-[0.2rem] 
                  leading-[2.15] outline-none transition-all duration-200 ease-linear 
                  focus:placeholder:opacity-100 
                  motion-reduce:transition-none
                  data-[te-input-state-active]:placeholder:opacity-100 
                  [&:not([data-te-input-placeholder-active])]:placeholder:opacity-0" />
                <label for="extimg_id" class="pointer-events-none absolute left-3 top-0 mb-0 
                  max-w-[90%] origin-[0_0] truncate pt-[0.33rem] leading-[2.15] 
                  text-neutral-600 transition-all duration-200 ease-out 
                  peer-focus:-translate-y-[1.15rem] 
                  peer-focus:scale-[0.8] peer-focus:text-primary 
                  peer-data-[te-input-state-active]:-translate-y-[1.15rem] 
                  peer-data-[te-input-state-active]:scale-[0.8] 
                  motion-reduce:transition-none">
                    [#text Full image url#]
                </label>
            </div>

            <div class="rounded p-4 mt-6 relative border border-neutral-300 min-h-[100px]">
                <h3 class="absolute -top-[9px] left-[6px] text-xs text-neutral-400 bg-neutral-50 px-1">
                    [#text Preview#]
                </h3>

                <template x-if="embed_info.active=='extimg' 
                    && embed_info.extimg.url && embed_info.extimg.url.length > 10">
                    <div data-nb-embed="extimg">
                        <div>
                            <img :src="embed_info.extimg.url">
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>