[#set selected=[#includes option.x record.[#item.key#]#] overwrite#]

<option value="[#option.x#]" [#if selected=(not-empty) echo=selected#]>
    [#text [#option.key#]#]
</option>