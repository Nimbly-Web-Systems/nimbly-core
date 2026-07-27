[#get-resource-meta [#resource-id#]#]
[#get-resource-record [#resource-id#] [#get uuid#]#]
<script>
    var _resource_url="[#get _resource_url default=[#base-url#]/nb-admin/[#resource-id#]#]";
    var _initial_lang="[#get record.lang default=en#]"
    var _translation_mode="[#get translation_mode#]";
    var _ai_translate_record=[#if data.ai_translate_record=(not-empty) echo=true echo_else=false#];
    var _frecord=[#fmt var=_frecord json#];
    [#include file=[#base-path#]core/modules/admin/tpl/edit-resource-form/form_edit.js#]
</script>
[#edit-resource-form#]
