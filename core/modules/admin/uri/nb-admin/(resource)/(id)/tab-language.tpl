<li>
    [#if translations.[#item.key#]=(not-empty) translation_mode=record tpl=tab-language-view#]
    [#if translations.[#item.key#]=(empty) translation_mode=record tpl=tab-language-create#]
    [#if translations.[#item.key#]=(not-empty) translation_mode=field tpl=tab-language-view-2#]
    [#if translations.[#item.key#]=(empty) translation_mode=field tpl=tab-language-create-2#]
</li>