<div class="mb-4">
    [#init-field#]
    [#if item.multi=(not-empty) tpl=multi-field#]
    [#if not item.multi=(not-empty) item.type=html tpl=set-value#]
    [#if item.multi=(empty) tpl=[#item.type#]-field#]
</div>