<div class="flex flex-row align-middle">
    <button type="submit" class="[#btn-class-primary#] mb-4 flex flex-row align-middle" disabled="true"
        x-bind:disabled="uploading">
        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="animate-spin w-5 h-5" x-cloak
            x-show="uploading">
            <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                fill="#ffffff" />
            <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#ffffff" />
        </svg>
        <div class="text-sm font-bold px-2">[#text [#_ftitle#]#]</div>
    </button>

    <div x-cloak x-show="uploading" class="text-xs pt-2 pl-2">[#text Sending#]</div>
</div>