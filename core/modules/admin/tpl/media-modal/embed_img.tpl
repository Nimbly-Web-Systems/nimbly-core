<!-- https://picsum.photos/200/300 -->

<div class="collapse collapse-arrow rounded-none border border-t-0 border-neutral-200 bg-neutral-50"
    :class="embed_info.active=='extimg' ? 'collapse-open' : 'collapse-close'">
    <button @click="embed_info.active='extimg'" class="collapse-title flex w-full items-center gap-2 px-5 py-4 text-left text-base text-neutral-800"
        type="button" :aria-expanded="embed_info.active=='extimg'" aria-controls="extimg_id">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
            <path fill-rule="evenodd"
                d="M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0 0 21 18v-1.94l-2.69-2.689a1.5 1.5 0 0 0-2.12 0l-.88.879.97.97a.75.75 0 1 1-1.06 1.06l-5.16-5.159a1.5 1.5 0 0 0-2.12 0L3 16.061Zm10.125-7.81a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Z"
                clip-rule="evenodd" />
        </svg>
        [#text Embed external image#]
    </button>
    <div id="extimg_id" class="collapse-content" x-show="embed_info.active=='extimg'" x-cloak>
        <div class="form-control w-full max-w-xs">
            <label for="extimg_url" class="label">
                <span class="label-text">[#text Full image url#]</span>
            </label>
            <input type="text" value="" name="extimg_id" id="extimg_url" placeholder="[#text Full image url#]"
                @keyup="embed_info.active='extimg'" x-model="embed_info.extimg.url"
                class="input input-bordered w-full bg-neutral-50" />
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
