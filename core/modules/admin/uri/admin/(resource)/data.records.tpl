<tr data-uuid="[record.key]">
    [set uuid=[record.key] overwrite]
    [repeat data.fields tpl=data.record var=field]
    <td>
         <a
                data-delete="[data.resource]/[uuid]"
                data-done='{
                    "hide": "\[data-uuid=[uuid]]",
                    "msg": "[resource-name [data.resource]] deleted successfully"
                }'>
                delete
            </a>
            <a href="[base-url]/admin/[data.resource]/[uuid]">edit</a>
    </td>
</tr>
