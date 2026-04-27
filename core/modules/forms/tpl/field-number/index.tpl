<div class="relative my-10">
    <input type="number" value="[#_f.value#]" name="[#_f.key#]" placeholder=""
        x-init="[#_f.model#]=`[#_f.value#]`"
        x-model="[#_f.model#]"
        [#if _f.required=(not-empty) echo=required#]
        [#if _f.min=(not-empty) echo="min='[#_f.min#]'"#]
        [#if _f.max=(not-empty) echo="max='[#_f.max#]'"#]
        class="input input-bordered w-full" />
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
