[#get-document-records resource=[#resource-id#]#]
<script>
    var _resource_id="[#resource-id#]";
    var _documents=[#fmt var=data.documents json#];
    var _documents_text={moved_to: "[#text Moved to#]", upload_failed: "[#text Upload failed#]"};
    [#include file=[#base-path#]core/modules/admin/tpl/document-explorer/document_explorer.js#]
</script>
