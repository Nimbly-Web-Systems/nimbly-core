[#set nb_actions_bar_side="[#get data.config.site.nimblybar.side default=left#]"#]
[#set nb_actions_tip="[#if nb_actions_bar_side=right echo=tooltip-right echo_else=tooltip-left#]"#]
<div id="nb-page-actions" class="nb-page-actions hidden [#if nb_actions_bar_side=right echo=left-4 echo_else=right-4#]">
    [#feature-cond edit-.config tpl=page-actions-settings#]
    [#feature-cond edit-inline-content tpl=page-actions-edit#]
</div>
<script>
    [#include file=[#base-path#]core/tpl/page-actions/index.js#]
</script>
