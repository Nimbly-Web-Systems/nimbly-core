<button type="button" class="flex flex-row uppercase text-xs text-gray-600 px-4 py-2 
    hover:font-bold hover:text-black cursor-pointer disabled:pointer-events-none disabled:text-gray-400"
    @click='busy=true;translate(`[#item.key#]`)'
    disabled="true" x-bind:disabled="busy" >
    +
    [#text [#item.key#]#]
    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-spin mx-1 w-4 h-4" x-cloak
        x-show="busy">
        <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
            d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
            fill="#333" />
        <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#333" />
    </svg>
    <span class="text-xs lowercase mx-1" x-cloak x-show="busy">Translating...</span>
</button>