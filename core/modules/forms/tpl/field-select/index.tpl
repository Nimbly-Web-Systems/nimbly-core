<div class="[#_f.wrapper_class#]">
    [#if _f.ai=(not-empty) tpl=ai-btn#]
    [#if _f.multi=(not-empty) tpl=multi#]
    [#if _f.multi=(empty) tpl=single#]
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800">
        [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
