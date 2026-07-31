<div class="[#_f.wrapper_class#]">
    <div class="flex items-center gap-2">
        <input type="[#_f.type#]" value="[#_f.value#]" name="[#_f.key#]" placeholder="" [#_f.x_init#]
            x-model="[#_f.model#]" [#if _f.required=(not-empty) echo=required#]
            class="input input-bordered min-w-0 flex-1" />
        [#field-actions#]
    </div>
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
