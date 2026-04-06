<input 
    class="focus:outline-none bg-transparent w-full" 
    type="text"
    name="[#item.key#]_slug"
    value="[#get record.[#item.key#]_slug#]"
    x-init="form_data.[#item.key#]_slug='[#get record.[#item.key#]_slug#]'"
    x-model="form_data.[#item.key#]_slug"
/>