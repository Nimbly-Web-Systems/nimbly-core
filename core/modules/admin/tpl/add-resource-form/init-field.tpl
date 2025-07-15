[#set _f.bg="bg-neutral-50" overwrite#]
[#set _f.title="[#text [#field-name name="[#_f.name#]"#]#]" overwrite#]
[#set _f.value="[#get record.[#_f.key#]#]" overwrite#]
[#set _f.model="form_data.[#_f.key#]" overwrite#]

