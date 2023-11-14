<section class="bg-neutral-50 p-4 sm:p-6 md:p-8 lg:p-10 font-lato">
    <h1 class="text-2xl md:text-3xl font-semibold ">[text Dashboard]</h1>
</section>
<section class="bg-neutral-100 px-4 sm:px-6 md:px-8 lg:px-10 py-4">
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 w-full">
        <div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
            <div class="flex items-start justify-between">
                <div class="text-lg font-lato font-medium truncate text-neutral-900">
                    [text Users]
                </div>
                <div class="relative" data-te-dropdown-ref>
                    <button class="rounded-full hover:bg-neutral-100 p-2" data-te-dropdown-toggle-ref>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                        </svg>

                    </button>
                    <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none bg-cdark bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                        aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                        <li>
                            <a class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal text-neutral-50 hover:bg-cnormal active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-400"
                                href="#" data-te-dropdown-item-ref>[text Manage users]</a>
                        </li>

                    </ul>
                </div>

            </div>
            <div class="text-7xl text-cnormal font-bold text-center mt-4 w-full">
                [data-count users]
            </div>
            <div class="text-lg text-cnormal font-bold text-center ">
                [text Accounts]
            </div>
            <div class="text-md text-neutral-500 text-center mt-4 ">
                [text Logged in]: <span class="font-bold text-lg">[get-sessions][count logged_in]</span>
            </div>
        </div>

        <div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
            <div class="flex items-start justify-between">
                <div class="text-lg font-lato font-medium truncate text-neutral-900">
                    [text Last update]
                </div>
                <div class="relative" data-te-dropdown-ref>
                    <button class="rounded-full hover:bg-neutral-100 p-2" data-te-dropdown-toggle-ref>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                        </svg>

                    </button>
                    <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none bg-cdark bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                        aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                        <li>
                            <a class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal text-neutral-50 hover:bg-cnormal active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-400"
                                href="#" data-te-dropdown-item-ref>[text Get updates]</a>
                        </li>
                    </ul>
                </div>

            </div>
            <div class="text-7xl text-orange-600 font-bold text-center mt-4 ">
                [date [last-update] fmt="d M"]
            </div>
            <div class="text-lg text-orange-600 font-bold text-center ">
                [ago [last-update]]
            </div>
            <div class="text-md text-neutral-500 text-center mt-4 ">
                [text Available updates]: <span class="font-bold text-lg">[get-sessions][count logged_in]</span>
            </div>
        </div>

    </div>

</section>