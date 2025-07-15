<div x-init='if (!form_data.[#_f.key#]) { form_data.[#_f.key#] = [#fmt var=record.[#_f.key#] json#]; }'></div>
[#set _f.value="[#get-i18n var=record.[#_f.key#] [#record.lang#] #]" overwrite#]