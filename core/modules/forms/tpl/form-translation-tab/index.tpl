<li><button type="button" class="uppercase text-xs text-gray-600 px-4 py-2 border-b-2 hover:font-bold hover:text-black cursor-pointer"
    :class="lang=='[#item.key#]'? 'border-b-primary' : 'border-b-transparent'"
    @click="switch_language('[#item.key#]')">
    [#text [#item.key#]#]
</button></li>
