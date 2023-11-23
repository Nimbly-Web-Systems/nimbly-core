<tr>
    [set uuid=[record.key] overwrite]

    [repeat data.fields tpl=data.record var=field nodot=true]
    [if data.fields=(empty) tpl=data.record.name]
    <td>
        <div class="flex items-center [if subkey=(not-empty) echo=hidden]">
            [feature-cond features="manage-[data.resource],delete_[data.resource],(any)_[data.resource]" tpl="action_delete"]
            [feature-cond features="manage-[data.resource],edit_[data.resource],(any)_[data.resource]" tpl="action_edit"]
        </div>
    </td>
</tr>