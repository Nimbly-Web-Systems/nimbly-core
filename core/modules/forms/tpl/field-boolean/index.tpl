<div class="[#_f.wrapper_class#] flex items-center gap-3">
    <input type="checkbox" name="[#_f.key#]" value="1"
        [#if _f.value=(not-empty) echo=checked#]
        x-model="[#_f.model#]"
        class="checkbox checkbox-sm" />
    <label for="[#_f.key#]" class="text-sm font-bold text-neutral-800">
        [#_f.title#]
        [#if _f.required=(not-empty) echo=" *"#]
    </label>
</div>
