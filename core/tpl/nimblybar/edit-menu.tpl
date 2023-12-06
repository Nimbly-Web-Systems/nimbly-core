<li class="relative mt-8 hidden" id="nb_edit_menu">
    <a class="flex h-[30px] rounded truncate 
        cursor-pointer items-center
        text-[0.875rem] text-neutral-100 outline-none transition duration-300 ease-linear
         hover:bg-clight hover:text-neutral-50 hover:outline-none focus:bg-clight
         focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50
         active:outline-none data-[te-sidenav-state-active]:text-neutral-50
          data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none" data-te-sidenav-link-ref
        id="nb_edit_toggler">

        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0" stroke="currentColor"
            class="w-[20px] h-[20px] ml-[5px]">
            <title>[text Edit]</title>
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
        </svg>

        <span class="group-[&[data-te-sidenav-slim-collapsed='true']]:data-[te-sidenav-slim='false']:hidden ml-[12px]"
            data-te-sidenav-slim="false">[text Edit]</span>
        <span class="absolute right-0 ml-auto mr-[0.5rem] transition-transform duration-300 ease-linear 
            motion-reduce:transition-none [&>svg]:text-neutral-100 " data-te-sidenav-rotate-icon-ref
            data-te-sidenav-slim="false">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                <path fill-rule="evenodd"
                    d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z"
                    clip-rule="evenodd" />
            </svg>
        </span>
    </a>
    <ul class="!visible relative mx-0 my-2 hidden list-none p-0 data-[te-collapse-show]:block "
        data-te-sidenav-collapse-ref>
        <li class='relative'>
            [if nb-skip-insert-media=(empty) tpl=media-btn]
            

            <button id="nb_edit_save" disabled data-te-ripple-init data-te-ripple-color="light"
                class="flex h-6 cursor-pointer items-center truncate rounded-[5px] py-4 pr-2 text-[0.8rem] text-neutral-100 
                outline-none transition 
                hover:disabled:bg-transparent
                disabled:text-white/50
                w-full
                duration-300 ease-linear hover:bg-clight/40 hover:text-neutral-50 hover:outline-none focus:bg-slate-50 
                focus:text-neutral-50 focus:outline-none active:bg-clight active:text-neutral-50 active:outline-none 
                data-[te-sidenav-state-active]:text-neutral-50 data-[te-sidenav-state-focus]:outline-none motion-reduce:transition-none" data-te-sidenav-link-ref="[base-url]/nb-admin/users">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                    stroke="currentColor" class="w-[23px] h-[23px] ml-[2px] -mt-[1px] mr-[12px] ">
                    <title>[text Save]</title>
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75l3 3m0 0l3-3m-3 3v-7.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                [text Save]
            </button>

        </li>
    </ul>
</li>
