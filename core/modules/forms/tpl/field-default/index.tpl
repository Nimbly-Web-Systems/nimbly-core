<div class="relative my-10">
    [#if _f.ai=(not-empty) tpl=ai-btn#]
    <input type="[#_f.type#]" value="[#_f.value#]" name="[#_f.key#]" placeholder="" x-init="[#_f.model#]=`[#_f.value#]`"
        x-model="[#_f.model#]" [#if _f.required=(not-empty) echo=required#] class="
            block peer w-full rounded border-0 bg-transparent px-2 py-1 outline outline-1 outline-neutral-300
            leading-loose transition-all duration-75
            focus:outline-2 focus:outline-primary
            " />
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>