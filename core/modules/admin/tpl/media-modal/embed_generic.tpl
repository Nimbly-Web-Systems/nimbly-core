<section x-show="mode !== 'select_embed'" class="mb-4 rounded border border-neutral-300 bg-neutral-50 p-5">
    <button type="button" class="text-base font-semibold" @click="embed_info.active='generic'" :aria-expanded="embed_info.active === 'generic'" aria-controls="nb-generic-controls">[#text Paste embed code#]</button>
    <div id="nb-generic-controls" x-show="embed_info.active === 'generic'" class="mt-4 space-y-4">
        <label class="block">[#text Embed code#]
            <textarea class="textarea textarea-bordered w-full" x-model="generic_code" @input.debounce.300ms="parse_generic()" rows="4" placeholder="&lt;iframe src=&quot;https://…&quot;&gt;&lt;/iframe&gt;"></textarea>
        </label>
        <p role="alert" class="text-error" x-show="generic_error" x-text="generic_error"></p>
        <template x-if="generic_info">
            <div class="space-y-4">
                <label class="block">[#text Accessible title#]<input class="input input-bordered w-full" x-model="generic_info.title" @change="preview_generic()" required></label>
                <p>[#text Width fills the content column.#]</p>
                <label class="block">[#text Height#]<input class="input input-bordered" type="number" min="1" max="10000" x-model.number="generic_info.height" @change="preview_generic()"></label>
                <label class="block"><input type="checkbox" class="checkbox checkbox-sm" x-model="generic_info.proportional" @change="preview_generic()"> [#text Proportional sizing for video#]</label>
                <label class="block" x-show="generic_info.proportional">[#text Original width#]<input class="input input-bordered" type="number" min="1" max="10000" x-model.number="generic_info.width" @change="preview_generic()"></label>
                <label class="block"><input type="checkbox" class="checkbox checkbox-sm" x-model="generic_info.sandbox" :disabled="generic_info.sandbox &amp;&amp; ![#feature-cond manage-unsandboxed-embeds echo=true echo_else=false#]"> [#text Sandbox enabled#]</label>
                <p x-show="!generic_info.sandbox">[#text External scripts can access this page and affect its behaviour.#]</p>
                <p x-show="generic_info.scripts.length">[#text Companion scripts do not run in this preview or in the editor. Save and view the page to check the complete embed.#]</p>
                <p>[#text Some services refuse framing or require broader permissions.#]</p>
            </div>
        </template>
        <div id="nb-generic-preview" class="w-full overflow-hidden"></div>
    </div>
</section>
