<div class="[#_f.wrapper_class#]">
    <input type="password"
        class="input input-bordered w-full"
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        placeholder="" />
    <label class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800">
        [#_f.title#][#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
