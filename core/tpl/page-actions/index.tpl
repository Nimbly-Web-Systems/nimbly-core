[#set nb_actions_bar_side="[#get data.config.site.nimblybar.side default=left#]"#]
<div id="nb-page-actions" class="nb-page-actions join hidden" role="toolbar" aria-label="[#text Page#]"
    data-corner="[#if nb_actions_bar_side=right echo=top-left echo_else=top-right#]">
    <button type="button" class="nb-page-actions-grip btn btn-sm join-item px-1" data-nb-page-actions-grip
        aria-label="[#text Move#]" title="[#text Move#]">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-4" aria-hidden="true">
            <circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" />
            <circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" />
        </svg>
    </button>
    [#feature-cond edit-inline-content tpl=page-actions-edit#]
    [#feature-cond edit-.config tpl=page-actions-settings#]
</div>
<script>
    [#include file=[#base-path#]core/tpl/page-actions/index.js#]
</script>
