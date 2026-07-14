<div class="[#_f.wrapper_class#]">
    <input type="color" value="[#_f.value#]" name="[#_f.key#]" [#_f.x_init#]
        x-model="[#_f.model#]" [#if _f.required=(not-empty) echo=required#]
        class="input input-bordered h-11 w-20 cursor-pointer p-1" />
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
