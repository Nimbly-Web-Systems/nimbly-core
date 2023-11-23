[set selected=[includes option.key record.[item.key]] overwrite]
<option value="[option.key]" [if selected=(not-empty) echo=selected]>[get option.name][get option.title]</option>