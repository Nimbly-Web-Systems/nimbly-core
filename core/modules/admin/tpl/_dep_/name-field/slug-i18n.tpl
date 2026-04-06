
<div x-init='if (!form_data.[#item.key#]_slug) { form_data.[#item.key#]_slug = [#fmt var=record.[#item.key#]_slug json empty={}#]; }'></div>

[#set _fvalue="[#get-i18n var=record.[#item.key#]_slug [#get record.lang#] #]" overwrite#]

<input type="text" name="[#item.key#]_slug" value="[#_fvalue#]"
	class="focus:outline-none bg-transparent w-full"
	x-init="form_data.[#item.key#]_slug[lang]=`[#_fvalue#]`"
	x-model="form_data.[#item.key#]_slug[lang]" />