<div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
    <div class="flex items-start justify-between">
        <div class="text-lg font-primary font-medium truncate text-neutral-900">
            [#text Media Library#]
        </div>
        <div class="relative [#feature-cond manage-content echo_else=hidden#]" data-te-dropdown-ref>
            <button class="rounded-full hover:bg-neutral-100 p-2 -mt-1" data-te-dropdown-toggle-ref>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                    <path
                        d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                </svg>
            </button>
            <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none
             bg-purple-800 bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                <li>
                    <a class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal
                         text-neutral-50 hover:bg-purple-700 active:text-white 
                         active:no-underline disabled:pointer-events-none disabled:bg-transparent
                          disabled:text-neutral-400"
                        href="[#base-url#]/nb-admin/media" data-te-dropdown-item-ref>[#text View files#]</a>
                </li>
                <li>
                    <form action="[#url#]" method="post" accept-charset="utf-8" id="ccache_thumbs">
                        [#form-key ccache_thumbs#]
                        <button type="submit" class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal
                         text-neutral-50 hover:bg-purple-700 active:text-white 
                         active:no-underline disabled:pointer-events-none disabled:bg-transparent
                          disabled:text-neutral-400">[#text Clear cache#] ([#fmt [#disk-space-thumbs#] bytes#])</button>
                    </form>
                </li>
                <li class="[#feature-cond manage-system echo_else=hidden#]">
                    <form action="[#url#]" method="post" accept-charset="utf-8" id="ccache_thumbs">
                        [#form-key delete_unusued_media#]
                        <button type="submit"
                        class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal
                         text-neutral-50 hover:bg-purple-700 active:text-white 
                         active:no-underline disabled:pointer-events-none disabled:bg-transparent
                          disabled:text-neutral-400">[#text Delete unused media#]</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
    <div class="text-7xl text-purple-800 font-bold text-center mt-4 w-full">
        [#data-count .files_meta#]
    </div>
    <div class="text-lg text-purple-800 font-bold text-center ">
        [#text Files#]
    </div>
    <div class="text-md text-neutral-500 text-center mt-4 ">
        [#text Last update#]: <span class="font-bold text-lg">[#fmt [#data-last-update .files_meta#] ago#]</span>
    </div>
    <div class="text-md text-neutral-500 text-center ">
        [#text Disk space#]: <span class="font-bold text-lg">[#fmt [#disk-space-resource .files#] bytes#]</span>
    </div>
</div>