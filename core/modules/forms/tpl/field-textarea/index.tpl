<div class="[#_f.wrapper_class#]">
    [#field-actions align=top#]
    <textarea name="[#_f.key#]" rows="[#get _f.rows default=3#]" placeholder=""
        [#_f.x_init#]
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        class="textarea textarea-bordered w-full [#if _f.has_actions=(not-empty) echo=pr-12#]">[#_f.value#]</textarea>
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
