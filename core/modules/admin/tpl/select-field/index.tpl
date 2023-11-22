<select data-te-select-init name="[item.key]" data-te-select-size="lg" [if item.multi=(not-empty) echo=multiple]
    class="[if item.hidden=(not-empty) echo=hidden]" x-model="form_data.[item.key]">
    <option value="(empty)">[text None]</option>
    [data [item.resource]]
    [repeat data.[item.resource] tpl=option var=option]
</select>
<label data-te-select-label-ref>[field-name name="[item.name]"]</label>