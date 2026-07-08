<button x-show="is_mobile" type="button"
    class="flex h-12 w-12 cursor-pointer items-center justify-center rounded text-white hover:bg-clight focus:bg-clight focus:outline-none md:hidden"
    @click="toggle_mobile_panel('app')" :aria-expanded="(mobile_panel === 'app').toString()" title="[#text App navigation#]">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
        class="h-5 w-5 shrink-0">
        <title>[#text App navigation#]</title>
        <path stroke-linecap="round" stroke-linejoin="round"
            d="M3.75 5.25a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 5.25a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM3.75 14.25a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5zM12.75 14.25a1.5 1.5 0 011.5-1.5h4.5a1.5 1.5 0 011.5 1.5v4.5a1.5 1.5 0 01-1.5 1.5h-4.5a1.5 1.5 0 01-1.5-1.5v-4.5z" />
    </svg>
</button>
