<button type="button" class="[#btn-class-secondary#] min-h-11 sm:min-h-0"
    x-bind:disabled="busy"
    @click="confirm('[#text Delete record. Are you sure?#]') && delete_record()">
    [#text [#_ftitle#]#]
</button>
