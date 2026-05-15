<div class="collapse collapse-arrow rounded-none border border-t-0 border-neutral-200 bg-neutral-50"
    :class="embed_info.active=='youtube' ? 'collapse-open' : 'collapse-close'">
    <button @click="embed_info.active='youtube'" class="collapse-title flex w-full items-center gap-2 px-5 py-4 text-left text-base text-neutral-800"
        type="button" :aria-expanded="embed_info.active=='youtube'" aria-controls="youtube_id">
        <svg width="256" height="256" viewBox="0 0 256 256" class="w-5 h-5" fill="currentColor">
            <g transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)">
                <path
                    d="M 88.119 23.338 c -1.035 -3.872 -4.085 -6.922 -7.957 -7.957 C 73.144 13.5 45 13.5 45 13.5 s -28.144 0 -35.162 1.881 c -3.872 1.035 -6.922 4.085 -7.957 7.957 C 0 30.356 0 45 0 45 s 0 14.644 1.881 21.662 c 1.035 3.872 4.085 6.922 7.957 7.957 C 16.856 76.5 45 76.5 45 76.5 s 28.144 0 35.162 -1.881 c 3.872 -1.035 6.922 -4.085 7.957 -7.957 C 90 59.644 90 45 90 45 S 90 30.356 88.119 23.338 z M 36 58.5 v -27 L 59.382 45 L 36 58.5 z" />
            </g>
        </svg>
        [#text Embed Youtube#]
    </button>
    <div id="youtube_id" class="collapse-content" x-show="embed_info.active=='youtube'" x-cloak>
        <div class="form-control w-full max-w-xs">
            <label for="youtube_id_input" class="label">
                <span class="label-text">[#text Youtube ID#]</span>
            </label>
            <input type="text" value="" name="youtube_id" id="youtube_id_input" placeholder="[#text Youtube ID#]"
                @keyup="embed_info.active='youtube'" x-model="embed_info.youtube.id"
                class="input input-bordered w-full bg-neutral-50" />
        </div>

        <div class="flex flex-row items-end gap-4 my-4 flex-wrap min-h-[40px]">
            <div class="text-neutral-600 mb-2">[#text Video size:#]</div>
            <div class="form-control">
                <label for="youtube_width" class="label py-1">
                    <span class="label-text text-xs">[#text Width#]</span>
                </label>
                <input type="number" x-model="embed_info.youtube.width" id="youtube_width"
                    placeholder="[#text Width#]" class="input input-bordered input-sm w-24 bg-neutral-50" />
            </div>
            <span class="mb-2">x</span>
            <div class="form-control">
                <label for="youtube_height" class="label py-1">
                    <span class="label-text text-xs">[#text Height#]</span>
                </label>
                <input type="number" x-model="embed_info.youtube.height" id="youtube_height"
                    placeholder="[#text Height#]" class="input input-bordered input-sm w-24 bg-neutral-50" />
            </div>
            <span class="mb-2">px</span>
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
