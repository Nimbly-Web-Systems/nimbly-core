<select class="select select-bordered w-full [#if _f.has_actions=(not-empty) echo=pr-12#]" name="[#_f.key#]"
    [#if _f.required=(not-empty) echo=required#]
    [#_f.x_init#]
    x-model="[#_f.model#]">
    <option value="(empty)">[#text None#]</option>
    [#if _f.resource=(not-empty) tpl=resource-options#]
    [#if _f.options=(not-empty) tpl=inline-options#]
</select>
