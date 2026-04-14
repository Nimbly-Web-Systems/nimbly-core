<option value="[#opt.key#]" [#if _f.value=(includes opt.key) echo=selected#]>
    [#get-i18n opt.name [#detect-language#]#][#get-i18n opt.title [#detect-language#]#]
</option>
