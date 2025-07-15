[#set _f.bg="bg-neutral-50" overwrite#]
[#set _f.ai=[#if _f.ai_prompts=(not-empty) echo=1#][#if _f.ai_prompts=(empty) echo=0#] overwrite#]
[#set _f.model="form_data.[#_f.key#][#if _f.i18n=(not-empty) echo=[lang]#]" overwrite#]
[#set _f.title="[#text [#field-name name="[#_f.name#]"#]#]" overwrite#]