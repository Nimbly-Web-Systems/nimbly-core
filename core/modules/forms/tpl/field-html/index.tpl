<div class="relative my-6">
    <div data-nb-edit="[#_f.key#]" 
        data-nb-edit-options='{
            "buttons":"[#get _f.buttons default=bold,italic#]", 
            "media_sizes":"[#get _f.media_sizes default=#]",
            "media": [#fmt var=_f.media type=boolean boolean=true|false#]}' 
            class="prose">
        [#get-html record.[#_f.key#] legacy-img-sizes=lg-70,xl-50#]
    </div>

    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-900 flex flex-row items-center">
            <div>
                [#_f.title#]
                [#if _f.required=(not-empty) echo=" * "#]
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="ml-1 w-4 h-4">
                <path fill-rule="evenodd"
                    d="M6.28 5.22a.75.75 0 010 1.06L2.56 10l3.72 3.72a.75.75 0 01-1.06 1.06L.97 10.53a.75.75 0 010-1.06l4.25-4.25a.75.75 0 011.06 0zm7.44 0a.75.75 0 011.06 0l4.25 4.25a.75.75 0 010 1.06l-4.25 4.25a.75.75 0 01-1.06-1.06L17.44 10l-3.72-3.72a.75.75 0 010-1.06zM11.377 2.011a.75.75 0 01.612.867l-2.5 14.5a.75.75 0 01-1.478-.255l2.5-14.5a.75.75 0 01.866-.612z"
                    clip-rule="evenodd" />
            </svg>
    </label>   
</div>