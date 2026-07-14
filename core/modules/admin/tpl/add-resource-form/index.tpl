[#get-resource-meta [#resource-id#]#]
<script>
    _resource_url="[#get _resource_url default=[#base-url#]/nb-admin/[#resource-id#]#]";
    var _initial_lang="[#get record.lang default=en#]";
    var _i18n_fields=[#fmt var=data.i18n_fields json#];
    [#include file=[#base-path#]core/modules/admin/tpl/add-resource-form/form_add.js#]
</script>
[#add-resource-form#]
        

