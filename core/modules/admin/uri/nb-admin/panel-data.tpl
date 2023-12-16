[#get-resources#]
<div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
    <div class="flex items-start justify-between">
        <div class="text-lg font-primary font-medium truncate text-neutral-900">
            [#text Data#]
        </div>
        <div class="relative [#feature-cond manage-users echo_else=hidden#]" data-te-dropdown-ref>
            <button class="rounded-full hover:bg-neutral-100 p-2 -mt-1" data-te-dropdown-toggle-ref>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                    <path
                        d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                </svg>

            </button>
            <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none bg-rose-800 bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                
                [#repeat data.user-resources tpl=resource-menu-item#]
            </ul>
        </div>
    </div>
    <div class="text-7xl text-rose-600 font-bold text-center mt-4 w-full">
        [#count data.user-resources#]
    </div>
    <div class="text-lg text-rose-600 font-bold text-center ">
        [#text Resources#]
    </div>
    <div class="text-md text-neutral-500 text-center mt-4 ">
        [#text All resources#]: <span class="font-bold text-lg">[#count data.resources#]</span>
    </div>
</div>