<div class="dropdown dropdown-end" :class="layout === 'list' && 'md:hidden'">
    <div tabindex="0" role="button" class="btn btn-sm btn-square" title="[#text Sort#]" aria-label="[#text Sort#]">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
        </svg>
    </div>
    <ul tabindex="0" class="dropdown-content menu z-20 mt-1 w-48 rounded-box border border-base-300 bg-base-100 p-1 shadow-sm">
        <template x-for="option in [
            { id: 'manual', name: '[#text Manual#]' },
            { id: 'title', name: '[#text Title#]' },
            { id: 'type', name: '[#text Type#]' },
            { id: 'size', name: '[#text Size#]' },
            { id: 'created', name: '[#text Created#]' },
            { id: 'modified', name: '[#text Modified#]' },
        ].filter((option) => option.id !== 'manual' || config.fields.position)" :key="option.id">
            <li>
                <button type="button" :class="sort_field === option.id && 'menu-active'" @click="choose_sort(option.id)">
                    <span class="grow text-left" x-text="option.name"></span>
                    <span x-show="is_sorted_asc(option.id) && option.id !== 'manual'" aria-hidden="true">↑</span>
                    <span x-show="is_sorted_desc(option.id)" aria-hidden="true">↓</span>
                </button>
            </li>
        </template>
    </ul>
</div>
