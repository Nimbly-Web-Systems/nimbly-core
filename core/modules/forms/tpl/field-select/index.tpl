<div class="relative my-10">
    [#if _f.ai=(not-empty) tpl=ai-btn#]
    <select class="select border-neutral-300" name="[#_f.key#]" [#if item.multi=(not-empty) echo=multiple#] [#if _f.required=(not-empty)
        echo=required#] x-init="[#_f.model#]=`[#_f.value#]`" x-model="[#_f.model#]">
        <option value="(empty)">[#text None#]</option>
    </select>
    <label for="[#_f.key#]" class="pointer-events-none absolute left-3 -top-2.5 px-1
            font-bold text-sm leading-tight [#get _f.bg default=bg-neutral-50#]
            text-neutral-800
            peer-focus:text-cdark">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>