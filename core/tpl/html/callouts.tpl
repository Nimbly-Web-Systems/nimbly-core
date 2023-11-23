[system-messages]
<div id="nb-system-messages"
    class="fixed top-0 w-full md:w-[330px] z-[1102] left-0 right-0 md:left-[calc(50%-165px)] my-4 rounded-lg bg-yellow-100 shadow-lg px-6 py-5 text-base text-neutral-900 [if system_message=(empty) echo=hidden]"
    data-te-alert-init role="alert">
    <button type="button"
        class="float-right ml-auto box-content rounded-none border-none p-1 text-warning-900 opacity-50 hover:text-warning-900 hover:no-underline hover:opacity-75 focus:opacity-100 focus:shadow-none focus:outline-none"
        aria-label="Close" onclick="nb.hide_notification()">
        <span
            class="w-[1em] focus:opacity-100 disabled:pointer-events-none disabled:select-none disabled:opacity-25 [&.disabled]:pointer-events-none [&.disabled]:select-none [&.disabled]:opacity-25">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6">
                <path fill-rule="evenodd"
                    d="M5.47 5.47a.75.75 0 011.06 0L12 10.94l5.47-5.47a.75.75 0 111.06 1.06L13.06 12l5.47 5.47a.75.75 0 11-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 01-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 010-1.06z"
                    clip-rule="evenodd" />
            </svg>
        </span>
    </button>
    <p class="py-1">
        [get system_message]
    </p>
</div>