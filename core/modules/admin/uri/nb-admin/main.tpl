<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800">[text Dashboard]</h1>
    <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700">[text dashboard-subtitle]</h3>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 w-full">
        <div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden">
            <div class="flex items-start justify-between">
                <div class="text-lg font-primary font-medium truncate text-neutral-900">
                    [text Users]
                </div>
                <div class="relative [feature-cond manage-users echo_else=hidden]" data-te-dropdown-ref>
                    <button class="rounded-full hover:bg-neutral-100 p-2 -mt-1" data-te-dropdown-toggle-ref>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                        </svg>

                    </button>
                    <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none bg-cdark bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                        aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                        <li>
                            <a class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal
                                 text-neutral-50 hover:bg-cnormal active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-400"
                                href="[base-url]/nb-admin/users" data-te-dropdown-item-ref>[text Manage users]</a>
                        </li>
                        <li>
                            <a class="block w-full whitespace-nowrap bg-transparent px-4 py-2 text-sm font-normal
                                 text-neutral-50 hover:bg-cnormal active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-400"
                                href="[base-url]/nb-admin/users/add" data-te-dropdown-item-ref>[text Add user]</a>
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

        <div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden" x-data="updates">
            <div class="flex items-start justify-between">
                <div class="flex flex-row items-center">
                    <div class="text-lg font-primary font-medium truncate text-neutral-900 min-w-[100px]">
                        [text Last system update]
                    </div>
                    <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"
                        class="animate-spin w-6 h-6" x-cloak x-show="busy">
                        <path opacity="0.2" fill-rule="evenodd" clip-rule="evenodd"
                            d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                            fill="#000000" />
                        <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="#000000" />
                    </svg>
                </div>
                <div class="relative [feature-cond manage-system echo_else=hidden]" data-te-dropdown-ref>
                    <button class="rounded-full hover:bg-neutral-100 p-2 -mt-1" data-te-dropdown-toggle-ref>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
                            <path
                                d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM11.5 15.5a1.5 1.5 0 10-3 0 1.5 1.5 0 003 0z" />
                        </svg>

                    </button>
                    <ul class="absolute z-[1000] float-left m-0 hidden min-w-max list-none overflow-hidden rounded-lg border-none bg-orange-500 bg-clip-padding text-left text-base shadow-lg [&[data-te-dropdown-show]]:block"
                        aria-labelledby="dropdownMenuSmallButton" data-te-dropdown-menu-ref>
                        <li>
                            <button
                                class="block w-full whitespace-nowrap bg-transparent px-4 py-2 
                                text-sm font-normal text-neutral-50 hover:bg-orange-400 disabled:cursor-auto
                                 active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-200"
                                data-te-dropdown-item-ref @click="pull_site" :disabled="site_updates==0">
                                [text Get site updates] (<span x-text="site_updates"></span>)</button>
                        </li>
                        <li>
                            <button
                                class="block w-full whitespace-nowrap bg-transparent disabled:cursor-auto px-4 py-2 text-sm font-normal text-neutral-50 hover:bg-orange-400 active:text-white active:no-underline disabled:pointer-events-none disabled:bg-transparent disabled:text-neutral-200"
                                data-te-dropdown-item-ref @click="pull_core" :disabled="core_updates==0">
                                [text Get core updates] (<span x-text="core_updates"></span>)</button>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="text-7xl text-orange-600 font-bold text-center mt-4 -ml-4">
                [date [last-update] fmt="d"]
                <span class="text-3xl uppercase">[date [last-update] fmt="M"]</span>
            </div>
            <div class="text-lg text-orange-600 font-bold text-center ">
                [ago [last-update]]
            </div>
            <div class="text-md text-neutral-500 text-center mt-4 ">
                [text Available site updates]:
                <span class="font-bold text-lg" x-text="site_updates"></span>
            </div>
            <div class="text-md text-neutral-500 text-center">
                [text Core updates]:
                <span class="font-bold text-lg" x-text="core_updates"></span>
            </div>
        </div>

        <div class="flex flex-col flex-auto p-6 bg-neutral-50 shadow rounded-2xl overflow-hidden"> 
            <div class="text-lg font-primary font-medium truncate text-neutral-900">
                [text Notes]
            </div>
            
            <div class="prose" data-nb-edit="[cfield notes]" 
                data-nb-edit-options='{"buttons":"bold,italic,orderedlist,unorderedlist,quote","media":true,"media_sizes":"md-50,lg-33,xl-25"}'>
                [render notes default="[text Type here]"]
            </div>

        </div>
    </div>
</section>
