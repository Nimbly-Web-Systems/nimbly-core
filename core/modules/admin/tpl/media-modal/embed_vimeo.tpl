<div class="collapse collapse-arrow rounded-t-lg border border-neutral-200 bg-neutral-50 text-neutral-600"
    :class="embed_info.active=='vimeo' ? 'collapse-open' : 'collapse-close'">
    <button @click="embed_info.active='vimeo'" class="collapse-title flex w-full items-center gap-2 px-5 py-4 text-left text-base text-neutral-800"
        type="button" :aria-expanded="embed_info.active=='vimeo'" aria-controls="vimeo_id">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-4 h-4" fill="currentColor">
            <path
                d="M22.875 10.063c-2.442 5.217-8.337 12.319-12.063 12.319-3.672 0-4.203-7.831-6.208-13.043-.987-2.565-1.624-1.976-3.474-.681l-1.128-1.455c2.698-2.372 5.398-5.127 7.057-5.28 1.868-.179 3.018 1.098 3.448 3.832.568 3.593 1.362 9.17 2.748 9.17 1.08 0 3.741-4.424 3.878-6.006.243-2.316-1.703-2.386-3.392-1.663 2.673-8.754 13.793-7.142 9.134 2.807z" />
        </svg>
        [#text Embed Vimeo#]
    </button>
    <div id="vimeo_id" class="collapse-content" x-show="embed_info.active=='vimeo'">
        <p class="text-sm mb-4 max-w-[75ch] text-neutral-500">[#text vimeo_embed_help#]</p>
        <div class="flex flex-row items-center flex-wrap gap-2">
            <div class="form-control w-full max-w-xs">
                <label for="vimeo_id_input" class="label">
                    <span class="label-text">[#text Vimeo ID#]</span>
                </label>
                <input type="text" value="" name="vimeo_id" id="vimeo_id_input" placeholder="[#text Vimeo ID#]"
                    x-model="embed_info.vimeo.id" @keyup="embed_info.active='vimeo'"
                    class="input input-bordered w-full bg-neutral-50" />
            </div>
            <div class="form-control w-full max-w-xs">
                <label for="vimeo_hash" class="label">
                    <span class="label-text">[#text Vimeo Hash#]</span>
                </label>
                <input type="text" value="" name="vimeo_hash" id="vimeo_hash" placeholder="[#text Vimeo Hash#]"
                    x-model="embed_info.vimeo.hash" class="input input-bordered w-full bg-neutral-50" />
            </div>
        </div>

        <div class="flex flex-row items-center gap-4 my-4 flex-wrap min-h-[40px]">
            <div class="text-neutral-600">[#text Video size:#]</div>

            <label class="label cursor-pointer gap-2">
                <input x-model="embed_info.vimeo.mode" value="responsive" checked type="radio"
                    name="vimeo_embed_mode" class="radio radio-primary radio-sm" />
                <span class="label-text">[#text Responsive#]</span>
            </label>

            <label class="label cursor-pointer gap-2">
                <input value="fixed" x-model="embed_info.vimeo.mode" type="radio" name="vimeo_embed_mode"
                    class="radio radio-primary radio-sm" />
                <span class="label-text">[#text Fixed size#]</span>
            </label>

            <div class="flex items-end gap-2" x-show="embed_info.vimeo.mode=='fixed'">
                <div class="form-control">
                    <label for="vimeo_width" class="label py-1">
                        <span class="label-text text-xs">[#text Width#]</span>
                    </label>
                    <input type="number" x-model="embed_info.vimeo.width" id="vimeo_width"
                        placeholder="[#text Width#]" class="input input-bordered input-sm w-24 bg-neutral-50" />
                </div>
                <span class="mb-2">x</span>
                <div class="form-control">
                    <label for="vimeo_height" class="label py-1">
                        <span class="label-text text-xs">[#text Height#]</span>
                    </label>
                    <input type="number" x-model="embed_info.vimeo.height" id="vimeo_height"
                        placeholder="[#text Height#]" class="input input-bordered input-sm w-24 bg-neutral-50" />
                </div>
                <span class="mb-2">px</span>
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
