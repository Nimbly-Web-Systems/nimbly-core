<div x-init='if (!form_data.[#item.key#]) { form_data.[#item.key#] = [#fmt var=record.[#item.key#] json#]; }'></div>
[#set _fvalue="[#get-i18n var=record.[#item.key#] [#record.lang#] #]" overwrite#]