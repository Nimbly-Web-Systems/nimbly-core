<option value="[#opt.key#]" [#if _f.value=(includes opt.key) echo=selected#]>
    [#resource-title resource=[#_f.resource#] uuid=[#opt.key#]#]
</option>
