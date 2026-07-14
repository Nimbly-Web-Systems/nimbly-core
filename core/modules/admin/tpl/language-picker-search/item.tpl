<li x-show="$el.dataset.label.includes(query.toLowerCase())" data-label="[#item.key#]">
    <button type="button"
        class="block w-full px-3 py-2 text-left text-sm uppercase hover:bg-neutral-100"
        :class="lang=='[#item.key#]' && 'font-bold'"
        @click="lang='[#item.key#]'; open=false; query=''">
        [#text [#item.key#]#]
    </button>
</li>
