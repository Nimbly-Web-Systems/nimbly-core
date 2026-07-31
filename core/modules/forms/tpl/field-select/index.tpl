<div class="[#_f.wrapper_class#]">
    <div class="flex items-center gap-2">
        <div class="min-w-0 flex-1">
            [#if _f.multi=(not-empty) tpl=multi#]
            [#if _f.multi=(empty) tpl=single#]
        </div>
        [#field-actions#]
    </div>
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800">
        [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
