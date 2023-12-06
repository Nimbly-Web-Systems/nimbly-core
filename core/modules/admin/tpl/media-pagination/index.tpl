<nav aria-label="media nav">
    <ul class="list-style-none flex gap-2">
        <li>
            <button :disabled="first === 1" @click="set_page(current_page - 1)" class="h-9 w-9 flex items-center justify-center rounded
           hover:bg-clight/20 
           disabled:text-neutral-300 disabled:pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                </svg>
            </button>
        </li>
        <li>
            <button :disabled="last >= files.length" @click="set_page(current_page + 1)" class="h-9 w-9  flex items-center justify-center rounded
         hover:bg-clight/20 
         disabled:text-neutral-300 disabled:pointer-events-none ">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                    stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
            </button>
        </li>
    </ul>
</nav>