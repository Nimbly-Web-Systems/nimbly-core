[#set selected=[#includes option.ix record.[#item.key#]#] overwrite#]
<option value="[#option.ix#]" [#if selected=(not-empty) echo=selected#]>
    [#text [#option.key#]#]
</option>