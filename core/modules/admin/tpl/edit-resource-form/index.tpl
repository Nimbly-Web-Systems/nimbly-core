<script>
    var _resource_url="[#get _resource_url default=[#base-url#]/nb-admin/[#recource-name#]#]";
    var _initial_lang="[#get record.lang default=en#]"
    [#include file=[#base-path#]core/modules/admin/tpl/edit-resource-form/form_edit.js#]
</script>
[#get-resource-meta [#resource-name#]#]
[#get-resource-record [#resource-name#] [#uuid#]#]
[#edit-resource-form#]