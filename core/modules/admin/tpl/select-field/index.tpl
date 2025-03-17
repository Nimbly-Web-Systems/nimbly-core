<select data-te-select-init name="[#item.key#]" data-te-select-size="lg" [#if item.multi=(not-empty) echo=multiple#]
    class="[#if item.hidden=(not-empty) echo=hidden#]" 
    [#if item.required=(not-empty) or _frequired=(not-empty) echo=required#]
    x-init='form_data.[#item.key#]=[#get record.[#item.key#] default=(empty) json#]'
    x-model="form_data.[#item.key#]">
    <option value="(empty)">[#text None#]</option>
    [#repeat item.options tpl=option_item var=option#]
    [#data [#get item.resource#]#]
    [#repeat data.[#get item.resource#] tpl=option_data var=option#]
    [#get _foptions default=""#]
    
</select>
<label data-te-select-label-ref class="[#_fbg#] z-10">[#field-name name="[#item.name#]"#]</label>
<div class="h-4"></div>

