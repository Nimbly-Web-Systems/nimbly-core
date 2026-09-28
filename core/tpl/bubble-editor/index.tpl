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
