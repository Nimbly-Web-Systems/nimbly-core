[#get-resource-records resource=[#resource-name#] role=table#]
<script>
    var _resource_id="[#resource-name#]";
    var _records=[#fmt var=data.records empty={} json#];
    var _fields=[#fmt var=data.fields json#];
    var _page_size=[#get api_datatable.entries default=50#];
    [#include file=[#base-path#]core/modules/admin/tpl/resource-table/data_table.js#]
</script>