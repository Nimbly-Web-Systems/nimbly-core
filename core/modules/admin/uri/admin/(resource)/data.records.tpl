<tr data-uuid="[record.key]">
    [set uuid=[record.key] overwrite]
    [repeat data.fields tpl=data.record var=field]
	[if data.fields=(empty) tpl=data.record.name]
    <td class="action-cell">
    	[if subkey=(not-empty) echo="<div class='nb-close'>"]
            [feature-cond features="manage-[data.resource],delete_[data.resource],(any)_[data.resource]" tpl="delete"]
            [feature-cond features="manage-[data.resource],edit_[data.resource],(any)_[data.resource]" tpl="edit"]
        [if subkey=(not-empty) echo="</div>"]
    </td>
</tr>
