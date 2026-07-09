[#set nbar_side="[#get data.config.site.nimblybar.side default=right#]"#]
[#set nbar_collapsed="[#if api_nb_bar_slim=(empty) echo=false echo_else=true#]"#]
[#set nimblybar-mobile-app-enabled=#]
[#set nimblybar-mobile-app=#]
[#nimblybar-mobile-app#]
[#set nbar-page-settings-in-edit=#]
[#feature-cond edit-inline-content tpl=set-page-settings-in-edit#]

<script>
    (function () {
        function set_page_layout() {
            var side = "[#nbar_side#]" === "left" ? "left" : "right";
            var collapsed = [#nbar_collapsed#];
            var mobile = window.matchMedia("(max-width: 767px)").matches;
            var offset = collapsed ? "2rem" : "15rem";
            var page = document.getElementById("page");
            if (!document.body) {
                return false;
            }
            if (page) {
                page.style.width = "";
                page.style.marginLeft = "";
                page.style.marginRight = "";
            }
            document.body.classList.add("nb-bar-layout");
            document.body.style.boxSizing = "border-box";
            document.body.style.paddingLeft = !mobile && side === "left" ? offset : "";
            document.body.style.paddingRight = !mobile && side === "right" ? offset : "";
            document.body.style.paddingBottom = mobile ? "4rem" : "";
            return true;
        }
        if (!set_page_layout()) {
            window.addEventListener("DOMContentLoaded", set_page_layout);
        }
        window.addEventListener("resize", set_page_layout);
    })();
</script>

<style>
    body.nb-bar-layout {
        transition: padding-left 200ms ease-in-out, padding-right 200ms ease-in-out, padding-bottom 200ms ease-in-out;
    }
</style>

<nav id="nb-bar" x-data="nimblybar('[#nbar_side#]', [#nbar_collapsed#])"
    class="fixed bottom-0 left-0 right-0 z-[1035] overflow-visible border-t border-cbar bg-cbar text-white font-primary shadow-[0_4px_12px_0_rgba(0,0,0,0.07),_0_2px_4px_rgba(0,0,0,0.05)] transition-all duration-200 ease-in-out md:top-0 md:bottom-auto md:h-screen [#if nbar_side=left echo=md:left-0 md:right-auto md:border-r#][#if nbar_side=left echo_else=md:right-0 md:left-auto md:border-l#]"
    :class="collapsed ? 'md:w-8' : 'md:w-60 md:px-2'">

    <div class="flex h-16 flex-col overflow-visible md:h-full md:overflow-hidden" :class="collapsed ? 'md:items-center md:pt-3' : 'md:items-stretch md:pt-3'">
        [#if nimblybar-mobile-app-enabled=(not-empty) tpl=menu-mobile-app#]
        [#feature-cond view-admin-dashboard tpl=menu-mobile-resources#]
        [#feature-cond edit-inline-content tpl=menu-mobile-edit#]
        [#menu-mobile-profile#]

        <div class="flex h-16 shrink-0 items-center justify-between gap-1 px-2 md:h-8 md:gap-2 md:px-0" :class="collapsed ? 'md:w-8 md:justify-start' : 'md:justify-start'">
            <button id="nb_nav_toggler"
                class="hidden h-12 w-12 cursor-pointer items-center justify-center rounded text-white hover:bg-clight focus:bg-clight focus:outline-none md:flex md:h-8 md:w-8"
                :style="collapsed && !is_mobile ? 'width: 32px; min-width: 32px;' : ''"
                type="button" @click="toggle" :title="collapsed ? '[#text Expand menu#]' : '[#text Collapse menu#]'">
                <svg aria-hidden="true" focusable="false" class="h-5 w-5 shrink-0 fill-white" role="img"
                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                    <title>[#text Toggle menu#]</title>
                    <path fill="#fff"
                        d="M16 132h416c8.837 0 16-7.163 16-16V76c0-8.837-7.163-16-16-16H16C7.163 60 0 67.163 0 76v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16z">
                    </path>
                </svg>
            </button>

            [#if nimblybar-mobile-app-enabled=(not-empty) tpl=btn-mobile-app#]
            [#if nimblybar-mobile-app-enabled=(not-empty) tpl=btn-site-home-desktop#]
            [#if nimblybar-mobile-app-enabled=(empty) tpl=btn-site-home#]

            [#feature-cond view-admin-dashboard tpl=btn-dashboard#]
            [#feature-cond view-admin-dashboard tpl=btn-mobile-resources#]
            [#feature-cond edit-inline-content tpl=btn-mobile-edit#]
            [#feature-cond edit-.config tpl=btn-page-settings#]

            <div x-show="!collapsed || is_mobile" class="relative md:ml-auto" @click.outside="account_open = false" id="nb-bar-account-menu">
                <button id="nb_account_btn" type="button" @click="is_mobile ? toggle_mobile_panel('profile') : (account_open = !account_open, mobile_panel = null)"
                    class="flex h-12 w-12 cursor-pointer items-center justify-center rounded text-white hover:bg-clight focus:bg-clight focus:outline-none md:h-8 md:w-8"
                    aria-haspopup="true" :aria-expanded="(is_mobile ? mobile_panel === 'profile' : account_open).toString()" title="[#text Profile#]">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                        stroke="currentColor" class="h-5 w-5 shrink-0">
                        <title>[#text Profile#]</title>
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>
                <div x-cloak x-show="account_open" x-transition
                    class="fixed bottom-16 right-2 z-[1100] w-[min(18rem,calc(100vw-1rem))] overflow-hidden rounded-lg bg-neutral-50 text-neutral-700 shadow-lg md:absolute md:top-9 md:right-0 md:bottom-auto md:w-[180px]">
                    <p class="px-4 pb-2 pt-4 text-xs text-neutral-500">
                        [#text Logged in as#] <br />
                        <span class="text-neutral-700">[#username#]</span>
                    </p>
                    <hr class="my-2 h-0 border border-t-0 border-solid border-neutral-700 opacity-10" />
                    <a class="flex w-full items-center whitespace-nowrap bg-transparent p-2 text-sm font-normal text-neutral-700 hover:bg-clight/20"
                        href="[#base-url#]/nb-admin/profile">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" data-slot="icon" class="w-6 h-6 mr-2">
                            <title>[#text Profile#]</title>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z">
                            </path>
                        </svg>
                        [#text Profile#]
                    </a>
                    <hr class="my-2 h-0 border border-t-0 border-solid border-neutral-700 opacity-10" />
                    <a class="flex w-full items-center whitespace-nowrap bg-transparent p-2 text-sm font-normal text-neutral-700 hover:bg-clight/20"
                        href="[#base-url#]/logout">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" data-slot="icon" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9">
                            </path>
                        </svg>
                        [#text Logout#]
                    </a>
                </div>
            </div>
        </div>

        <ul class="mt-2 hidden flex-1 flex-col gap-2 overflow-y-auto px-2 pb-2 md:mt-8 md:flex md:flex-none md:overflow-visible md:px-0 md:pb-0" x-show="!collapsed && !is_mobile" x-transition>
            [#feature-cond view-admin-dashboard tpl=menu-resources#]
            [#feature-cond edit-inline-content tpl=menu-edit#]
            [#set menu-ext=#]
            [#menu-ext#]
        </ul>

    </div>

    <a href="[#base-url#]/nb-admin" class="absolute bottom-10 z-[1090] hidden h-6 w-auto text-neutral-300 md:block"
        :class="side === 'left' ? '-right-5 rotate-[-90deg]' : '-left-5 rotate-90'" title="[#text Nimbly dashboard#]">
        <svg width="137px" height="47px" viewBox="0 0 137 47" version="1.1" class="h-6 w-auto"
            xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <path
                    d="M3.4140625,10.640625 L3.5078125,14.9765625 C4.46094227,13.4140547 5.64843039,12.2226604 7.0703125,11.4023437 C8.49219461,10.5820271 10.0624914,10.171875 11.78125,10.171875 C14.5000136,10.171875 16.5234309,10.9374923 17.8515625,12.46875 C19.1796941,14.0000077 19.8515624,16.2968597 19.8671875,19.359375 L19.8671875,36 L17.078125,36 L17.078125,19.3359375 C17.0624999,17.0703012 16.582036,15.382818 15.6367188,14.2734375 C14.6914015,13.164057 13.1796979,12.609375 11.1015625,12.609375 C9.36717883,12.609375 7.83203793,13.1523383 6.49609375,14.2382812 C5.16014957,15.3242242 4.1718782,16.7890533 3.53125,18.6328125 L3.53125,36 L0.7421875,36 L0.7421875,10.640625 L3.4140625,10.640625 Z M30.25,36 L27.4375,36 L27.4375,10.640625 L30.25,10.640625 L30.25,36 Z M26.9453125,3.3046875 C26.9453125,2.77343484 27.1171858,2.32422059 27.4609375,1.95703125 C27.8046892,1.58984191 28.2734345,1.40625 28.8671875,1.40625 C29.4609405,1.40625 29.933592,1.58984191 30.2851562,1.95703125 C30.6367205,2.32422059 30.8125,2.77343484 30.8125,3.3046875 C30.8125,3.83594016 30.6367205,4.2812482 30.2851562,4.640625 C29.933592,5.0000018 29.4609405,5.1796875 28.8671875,5.1796875 C28.2734345,5.1796875 27.8046892,5.0000018 27.4609375,4.640625 C27.1171858,4.2812482 26.9453125,3.83594016 26.9453125,3.3046875 Z M40.421875,10.640625 L40.515625,14.765625 C41.4531297,13.2343673 42.624993,12.0859413 44.03125,11.3203125 C45.437507,10.5546837 46.9999914,10.171875 48.71875,10.171875 C52.71877,10.171875 55.2578071,11.8124836 56.3359375,15.09375 C57.242192,13.5156171 58.4531174,12.3007855 59.96875,11.4492187 C61.4843826,10.597652 63.1562409,10.171875 64.984375,10.171875 C70.4219022,10.171875 73.195312,13.1405953 73.3046875,19.078125 L73.3046875,36 L70.4921875,36 L70.4921875,19.2890625 C70.4765624,17.0234262 69.9882861,15.3437555 69.0273438,14.25 C68.0664014,13.1562445 66.5000109,12.609375 64.328125,12.609375 C62.3124899,12.6406252 60.609382,13.2929624 59.21875,14.5664062 C57.828118,15.8398501 57.0546883,17.3906159 56.8984375,19.21875 L56.8984375,36 L54.0859375,36 L54.0859375,19.078125 C54.0703124,16.9062391 53.5585988,15.2851616 52.5507812,14.2148438 C51.5429637,13.1445259 49.992198,12.609375 47.8984375,12.609375 C46.1328037,12.609375 44.6171938,13.1132762 43.3515625,14.1210937 C42.0859312,15.1289113 41.1484405,16.6249901 40.5390625,18.609375 L40.5390625,36 L37.7265625,36 L37.7265625,10.640625 L40.421875,10.640625 Z M100.820312,23.578125 C100.820312,27.5312698 99.9453213,30.6679571 98.1953125,32.9882812 C96.4453037,35.3086054 94.0937648,36.46875 91.140625,36.46875 C87.6093573,36.46875 84.9375091,35.1562631 83.125,32.53125 L83.0078125,36 L80.3828125,36 L80.3828125,0 L83.171875,0 L83.171875,14.3203125 C84.9531339,11.5546737 87.5937325,10.171875 91.09375,10.171875 C94.093765,10.171875 96.464835,11.3163948 98.2070312,13.6054688 C99.9492275,15.8945427 100.820312,19.0781046 100.820312,23.15625 L100.820312,23.578125 Z M98.0078125,23.0859375 C98.0078125,19.7421708 97.3593815,17.1601654 96.0625,15.3398437 C94.7656185,13.5195221 92.9375118,12.609375 90.578125,12.609375 C88.781241,12.609375 87.2578188,13.0507768 86.0078125,13.9335937 C84.7578062,14.8164107 83.8125032,16.1093665 83.171875,17.8125 L83.171875,29.25 C84.578132,32.4375159 87.0624822,34.03125 90.625,34.03125 C92.9375116,34.03125 94.7460872,33.1171966 96.0507812,31.2890625 C97.3554753,29.4609284 98.0078125,26.7265807 98.0078125,23.0859375 Z M110.148438,36 L107.335938,36 L107.335938,0 L110.148438,0 L110.148438,36 Z M125.804688,31.96875 L133.210938,10.640625 L136.234375,10.640625 L125.40625,40.3125 L124.84375,41.625 C123.453118,44.7031404 121.304702,46.2421875 118.398438,46.2421875 C117.726559,46.2421875 117.007816,46.1328136 116.242188,45.9140625 L116.21875,43.59375 L117.671875,43.734375 C119.046882,43.734375 120.160152,43.3945346 121.011719,42.7148438 C121.863286,42.0351529 122.585935,40.8671958 123.179688,39.2109375 L124.421875,35.7890625 L114.859375,10.640625 L117.929688,10.640625 L125.804688,31.96875 Z"
                    fill="currentColor"></path>
            </g>
        </svg>
        <span class="sr-only">[#text Nimbly dashboard#]</span>
    </a>
</nav>

[#feature-cond view-.files tpl=media-modal-cond#]
[#feature-cond edit-.config tpl=modal-settings#]

<script>
    [#include file=[#base-path#]core/tpl/nimblybar/index.js#]
</script>
