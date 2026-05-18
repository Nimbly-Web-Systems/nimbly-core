[#get-resource-meta [#resource-id#]#]
[#get-resource-record [#resource-id#] [#uuid#]#]
<script>
    var _resource_url="[#get _resource_url default=[#base-url#]/nb-admin/[#resource-id#]#]";
    var _initial_lang="[#get record.lang default=en#]"
    var _frecord=[#fmt var=_frecord json#];
    [#include file=[#base-path#]core/modules/admin/tpl/edit-resource-form/form_edit.js#]
</script>
[#edit-resource-form#]
