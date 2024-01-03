<td>
	[#if subkey=(not-empty) echo="<a href='[#base-url#]/nb-admin/[#data.resource#]/[#get record.key#]'>"#]
	[#if subkey=(not-empty) echo="[#get subkey#]/"#]
	[#get record.name default=[#get record.title default=[#get record.key#]#]#]
	[#if subkey=(not-empty) echo="</a>"#]
</td>