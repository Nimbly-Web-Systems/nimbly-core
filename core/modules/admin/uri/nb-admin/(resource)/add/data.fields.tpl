<div class="mb-4">
    [#if item.multi=(not-empty) tpl=multi-field#]
    [#if item.multi=(empty) tpl=[#item.type#]-field#]
</div>
