<button type="button" class="uppercase text-xs text-gray-600 px-4 py-2 border-b-2 hover:font-bold hover:text-black cursor-pointer"
     :class="lang=='[#_f.key#]'? 'border-b-primary' : 'border-b-transparent'"
@click="save(); lang='[#_f.key#]'"
    >
    + [#text [#_f.key#]#]     
</button>