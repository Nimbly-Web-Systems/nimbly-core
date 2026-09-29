[#set nb_actions_bar_side="[#get data.config.site.nimblybar.side default=left#]"#]
<div id="nb-page-actions" class="nb-page-actions join hidden [#if nb_actions_bar_side=right echo=left-4 echo_else=right-4#]"
    role="toolbar" aria-label="[#text Page#]">
    [#feature-cond edit-inline-content tpl=page-actions-edit#]
    [#feature-cond edit-.config tpl=page-actions-settings#]
</div>
<script>
    [#include file=[#base-path#]core/tpl/page-actions/index.js#]
</script>
