[#set nb_actions_side="[#get data.config.site.nimblybar.side default=left#]"#]
[#set nb_actions_tip="[#if nb_actions_side=right echo=tooltip-left echo_else=tooltip-right#]"#]
<div id="nb-page-actions" class="nb-page-actions hidden [#if nb_actions_side=right echo=right-4 echo_else=left-4#]">
    [#feature-cond edit-.config tpl=page-actions-settings#]
    [#feature-cond edit-inline-content tpl=page-actions-edit#]
</div>
<script>
    [#include file=[#base-path#]core/tpl/page-actions/index.js#]
</script>
