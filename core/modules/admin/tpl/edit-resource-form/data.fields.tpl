<div class="mb-4">
    [#init-field#]
    [#if _f.multi=(not-empty) tpl=multi-field#]
    [#if not _f.multi=(not-empty) _f.type=html tpl=set-value[#if _f.i18n=(not-empty) echo=-i18n#]#]
    [#if _f.multi=(empty) tpl=field-[#_f.type#]#]
</div>