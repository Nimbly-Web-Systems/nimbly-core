[#get-resource-records resource=[#resource-id#] role=table#]
<script>
    var _resource_id="[#resource-id#]";
    var _records=[#fmt var=data.records empty={} json#];
    var _fields=[#fmt var=data.fields json#];
    var _default_sort_field='[#get data.sort.field default=#]';
    var _default_sort_order='[#get data.sort.order default=asc#]';
    var _page_size=[#get api_datatable.entries default=50#];
    [#include file=[#base-path#]core/modules/admin/tpl/resource-table/data_table.js#]
</script>
