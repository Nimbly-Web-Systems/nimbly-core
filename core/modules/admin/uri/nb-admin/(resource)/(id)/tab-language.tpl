<li>
    [#if translations.[#item.key#]=(not-empty) tpl=tab-language-view#]
    [#if translations.[#item.key#]=(empty) tpl=tab-language-create#]
</li>