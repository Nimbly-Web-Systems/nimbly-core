<template id="nb_bubble_toolbar">
    <div class="nb-bubble-toolbar hidden" role="toolbar">
        <span class="nb-bubble-arrow" aria-hidden="true"></span>
        <div class="join" data-nb-bubble-buttons></div>
        <form class="join hidden" data-nb-bubble-prompt>
            <input type="text" class="input input-sm join-item nb-bubble-input">
            <button type="submit" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="[#text Apply#]">&#10003;</button>
            <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-label="[#text Cancel#]" data-nb-bubble-cancel>&times;</button>
        </form>
    </div>
</template>
<template id="nb_bubble_button">
    <button type="button" class="btn btn-sm btn-square join-item nb-bubble-btn" aria-pressed="false"></button>
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
