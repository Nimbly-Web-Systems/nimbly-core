<template id="nb_bubble_toolbar">
    <div class="nb-bubble-toolbar hidden" role="toolbar">
        <span class="nb-bubble-arrow" aria-hidden="true"></span>
        <div class="join" data-nb-bubble-buttons></div>
        <div class="join hidden" role="group" data-nb-bubble-prompt>
            <input type="text" class="input input-sm join-item nb-bubble-input">
            <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="[#text Apply#]" data-nb-bubble-apply>&#10003;</button>
            <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn hidden" aria-label="[#text Remove link#]" title="[#text Remove link#]" data-nb-bubble-remove><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4" aria-hidden="true"><path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" x2="8" y1="2" y2="5"/><line x1="2" x2="5" y1="8" y2="8"/><line x1="16" x2="16" y1="19" y2="22"/><line x1="19" x2="22" y1="16" y2="16"/></svg></button>
            <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="[#text Cancel#]" data-nb-bubble-cancel>&times;</button>
        </div>
    </div>
</template>
<template id="nb_bubble_link_preview">
    <div class="nb-link-preview hidden" role="tooltip">
        <a class="nb-link-preview-url" target="_blank" rel="noopener noreferrer" data-nb-link-url></a>
        <button type="button" class="btn btn-xs nb-bubble-btn" data-nb-link-edit>[#text Edit#]</button>
        <button type="button" class="btn btn-xs btn-square nb-bubble-btn" aria-label="[#text Remove link#]" title="[#text Remove link#]" data-nb-link-remove><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5" aria-hidden="true"><path d="m18.84 12.25 1.72-1.71h-.02a5.004 5.004 0 0 0-.12-7.07 5.006 5.006 0 0 0-6.95 0l-1.72 1.71"/><path d="m5.17 11.75-1.71 1.71a5.004 5.004 0 0 0 .12 7.07 5.006 5.006 0 0 0 6.95 0l1.71-1.71"/><line x1="8" x2="8" y1="2" y2="5"/><line x1="2" x2="5" y1="8" y2="8"/><line x1="16" x2="16" y1="19" y2="22"/><line x1="19" x2="22" y1="16" y2="16"/></svg></button>
    </div>
</template>
<template id="nb_bubble_button">
    <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-pressed="false"></button>
</template>
<template id="nb_bubble_labels">
    <span data-name="bold">[#text Bold#]</span>
    <span data-name="italic">[#text Italic#]</span>
    <span data-name="h2">[#text Heading 2#]</span>
    <span data-name="h3">[#text Heading 3#]</span>
    <span data-name="h4">[#text Heading 4#]</span>
    <span data-name="quote">[#text Quote#]</span>
    <span data-name="orderedlist">[#text Numbered list#]</span>
    <span data-name="unorderedlist">[#text Bulleted list#]</span>
    <span data-name="anchor">[#text Link#]</span>
    <span data-name="strikethrough">[#text Strikethrough#]</span>
    <span data-name="subscript">[#text Subscript#]</span>
    <span data-name="superscript">[#text Superscript#]</span>
    <span data-name="underline">[#text Underline#]</span>
    <span data-name="pre">[#text Preformatted#]</span>
    <span data-name="removeFormat">[#text Clear formatting#]</span>
</template>
<script type="application/json" id="nb_bubble_buttons">[#bubble-editor-buttons#]</script>
<template id="nb_field_bar">
    <div class="nb-field-bar hidden" role="toolbar" aria-label="[#text Editor#]">
        <div class="join" data-nb-bar-format></div>
        <div class="join" data-nb-bar-insert>
            <button type="button" class="btn btn-sm join-item nb-bubble-btn gap-1.5" data-nb-bar-media>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                </svg>
                [#text Media#]
            </button>
        </div>
        <button type="button" class="btn btn-sm nb-field-bar-save gap-1.5" data-nb-bar-save data-nb-edit-save disabled>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
            [#text Save#]
        </button>
    </div>
</template>
