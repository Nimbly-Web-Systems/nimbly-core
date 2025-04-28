<ul class="mb-10 flex flex-row">
    <li><button type="button" 
        @click="lang='[#get record.lang#]'"
        class="uppercase text-xs px-4 py-2 border-b-2 hover:font-bold hover:text-black" 
        :class="lang=='[#get record.lang#]'? 'border-b-primary' : 'border-b-transparent'">[#text [#get record.lang#]#]</button></li>
    [#repeat languages tpl=tab-language#]
</ul>