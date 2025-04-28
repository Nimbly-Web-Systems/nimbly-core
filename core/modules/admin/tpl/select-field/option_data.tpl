[#set selected=[#includes option.key record.[#item.key#]#] overwrite#]
<option value="[#option.key#]" [#if selected=(not-empty) echo=selected#]>
   [#get-i18n option.name [#detect-language#]#]
   [#get-i18n option.title [#detect-language#]#]
</option>