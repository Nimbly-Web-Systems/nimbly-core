<button type="button"
    class="btn join-item btn-sm uppercase"
    :class="lang=='[#item.key#]' ? 'btn-primary' : 'btn-outline'"
    @click="lang='[#item.key#]'">
    [#text [#item.key#]#]
</button>
