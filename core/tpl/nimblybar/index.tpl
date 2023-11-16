<!-- Sidenav -->
<nav id="nb-bar" class="group fixed right-0 top-0 z-[1035] 
        h-screen w-60 -translate-x-full overflow-hidden bg-cnormal 
        shadow-[0_4px_12px_0_rgba(0,0,0,0.07),_0_2px_4px_rgba(0,0,0,0.05)] 
        data-[te-sidenav-slim='true']:hidden 
        data-[te-sidenav-slim-collapsed='true']:w-[30px] 
        data-[te-sidenav-slim='true']:w-[30px] 
        data-[te-sidenav-hidden='false']:translate-x-0 
        [&[data-te-sidenav-slim-collapsed='true'][data-te-sidenav-slim='false']]:hidden 
        [&[data-te-sidenav-slim-collapsed='true'][data-te-sidenav-slim='true']]:[display:unset]" data-te-sidenav-init
    data-te-sidenav-hidden="false" data-te-sidenav-mode="over" 
    data-te-sidenav-slim="[if api_nb_bar_slim=(empty) echo=false][if api_nb_bar_slim=(not-empty) echo=true]" 
    data-te-sidenav-right="true"
    data-te-sidenav-slim-width="30" data-te-sidenav-content="#main" 
    data-te-sidenav-slim-collapsed="[if api_nb_bar_slim=(empty) echo=false][if api_nb_bar_slim=(not-empty) echo=true]"
    data-te-sidenav-mode-breakpoint-side="md">

    <ul class="pt-3 relative" data-te-sidenav-menu-ref>

        <li class="h-[30px] block">
            <div class="flex items-center truncate w-full gap-2">
                <button class="inline-block rounded text-white w-[30px] h-[30px]
                hover:bg-clight" data-te-sidenav-link-ref aria-haspopup="true" id="nb_nav_toggler">
                    <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="bars"
                        class="w-[20px] h-[20px] ml-[5px] stroke-white fill-white" role="img"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512">
                        <title>[text Toggle menu]</title>
                        <path fill="#fff"
                            d="M16 132h416c8.837 0 16-7.163 16-16V76c0-8.837-7.163-16-16-16H16C7.163 60 0 67.163 0 76v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16zm0 160h416c8.837 0 16-7.163 16-16v-40c0-8.837-7.163-16-16-16H16c-8.837 0-16 7.163-16 16v40c0 8.837 7.163 16 16 16z">
                        </path>
                    </svg>
                </button>
                <div class="[if api_nb_bar_slim=(not-empty) echo=hidden] h-[30px] w-[30px] rounded truncate" data-te-sidenav-slim="false">
                    <a class="flex items-center align-middle justify-center h-[30px] w-[30px] text-white cursor-pointer
                    hover:bg-clight" href="[base-url]/">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                            stroke="currentColor" class="w-[22px] h-[22px] stroke-white">
                            <title>[text Site home]</title>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                    </a>
                </div>

                <div class="[if api_nb_bar_slim=(not-empty) echo=hidden] h-[30px] w-[30px] rounded truncate" data-te-sidenav-slim="false">
                    <a class="flex items-center  justify-center h-[30px] w-[30px] text-white cursor-pointer hover:bg-clight"
                        href="[base-url]/nb-admin">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.0"
                            stroke="currentColor" class="w-[23px] h-[23px] stroke-white">
                            <title>[text Admin dashboard]</title>
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 8.25V18a2.25 2.25 0 002.25 2.25h13.5A2.25 2.25 0 0021 18V8.25m-18 0V6a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 6v2.25m-18 0h18M5.25 6h.008v.008H5.25V6zM7.5 6h.008v.008H7.5V6zm2.25 0h.008v.008H9.75V6z" />
                        </svg>
                    </a>
                </div>

                <button id="nb_page_settings_btn" 
                    class="[if api_nb_bar_slim=(not-empty) echo=hidden] truncate text-white rounded w-[30px] h-[30px]
                hover:bg-clight" data-te-sidenav-slim="false" aria-haspopup="true">
                    <svg class="w-[23px] h-[23px]  fill-white flex-shrink-0 ml-[3px]" fill="#ffffff" height="48"
                        viewBox="0 0 24 24" width="48" xmlns="http://www.w3.org/2000/svg">
                        <title>[text Page settings]</title>
                        <path d="M0 0h24v24H0z" fill="none" />
                        <path
                            d="M19.43 12.98c.04-.32.07-.64.07-.98s-.03-.66-.07-.98l2.11-1.65c.19-.15.24-.42.12-.64l-2-3.46c-.12-.22-.39-.3-.61-.22l-2.49 1c-.52-.4-1.08-.73-1.69-.98l-.38-2.65C14.46 2.18 14.25 2 14 2h-4c-.25 0-.46.18-.49.42l-.38 2.65c-.61.25-1.17.59-1.69.98l-2.49-1c-.23-.09-.49 0-.61.22l-2 3.46c-.13.22-.07.49.12.64l2.11 1.65c-.04.32-.07.65-.07.98s.03.66.07.98l-2.11 1.65c-.19.15-.24.42-.12.64l2 3.46c.12.22.39.3.61.22l2.49-1c.52.4 1.08.73 1.69.98l.38 2.65c.03.24.24.42.49.42h4c.25 0 .46-.18.49-.42l.38-2.65c.61-.25 1.17-.59 1.69-.98l2.49 1c.23.09.49 0 .61-.22l2-3.46c.12-.22.07-.49-.12-.64l-2.11-1.65zM12 15.5c-1.93 0-3.5-1.57-3.5-3.5s1.57-3.5 3.5-3.5 3.5 1.57 3.5 3.5-1.57 3.5-3.5 3.5z" />
                    </svg>
                </button>





            </div>
        </li>
    </ul>

    <a href="[base-url]/nb-admin">
        <svg width="137px" height="47px" viewBox="0 0 137 47" version="1.1" xmlns="http://www.w3.org/2000/svg"
            xmlns:xlink="http://www.w3.org/1999/xlink"
            class="absolute rotate-90 h-[24px] w-auto -left-[20px] bottom-[40px] z-[1090]">
            <title>[text Nimbly dashboard]</title>
            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <path
                    d="M3.4140625,10.640625 L3.5078125,14.9765625 C4.46094227,13.4140547 5.64843039,12.2226604 7.0703125,11.4023437 C8.49219461,10.5820271 10.0624914,10.171875 11.78125,10.171875 C14.5000136,10.171875 16.5234309,10.9374923 17.8515625,12.46875 C19.1796941,14.0000077 19.8515624,16.2968597 19.8671875,19.359375 L19.8671875,36 L17.078125,36 L17.078125,19.3359375 C17.0624999,17.0703012 16.582036,15.382818 15.6367188,14.2734375 C14.6914015,13.164057 13.1796979,12.609375 11.1015625,12.609375 C9.36717883,12.609375 7.83203793,13.1523383 6.49609375,14.2382812 C5.16014957,15.3242242 4.1718782,16.7890533 3.53125,18.6328125 L3.53125,36 L0.7421875,36 L0.7421875,10.640625 L3.4140625,10.640625 Z M30.25,36 L27.4375,36 L27.4375,10.640625 L30.25,10.640625 L30.25,36 Z M26.9453125,3.3046875 C26.9453125,2.77343484 27.1171858,2.32422059 27.4609375,1.95703125 C27.8046892,1.58984191 28.2734345,1.40625 28.8671875,1.40625 C29.4609405,1.40625 29.933592,1.58984191 30.2851562,1.95703125 C30.6367205,2.32422059 30.8125,2.77343484 30.8125,3.3046875 C30.8125,3.83594016 30.6367205,4.2812482 30.2851562,4.640625 C29.933592,5.0000018 29.4609405,5.1796875 28.8671875,5.1796875 C28.2734345,5.1796875 27.8046892,5.0000018 27.4609375,4.640625 C27.1171858,4.2812482 26.9453125,3.83594016 26.9453125,3.3046875 Z M40.421875,10.640625 L40.515625,14.765625 C41.4531297,13.2343673 42.624993,12.0859413 44.03125,11.3203125 C45.437507,10.5546837 46.9999914,10.171875 48.71875,10.171875 C52.71877,10.171875 55.2578071,11.8124836 56.3359375,15.09375 C57.242192,13.5156171 58.4531174,12.3007855 59.96875,11.4492187 C61.4843826,10.597652 63.1562409,10.171875 64.984375,10.171875 C70.4219022,10.171875 73.195312,13.1405953 73.3046875,19.078125 L73.3046875,36 L70.4921875,36 L70.4921875,19.2890625 C70.4765624,17.0234262 69.9882861,15.3437555 69.0273438,14.25 C68.0664014,13.1562445 66.5000109,12.609375 64.328125,12.609375 C62.3124899,12.6406252 60.609382,13.2929624 59.21875,14.5664062 C57.828118,15.8398501 57.0546883,17.3906159 56.8984375,19.21875 L56.8984375,36 L54.0859375,36 L54.0859375,19.078125 C54.0703124,16.9062391 53.5585988,15.2851616 52.5507812,14.2148438 C51.5429637,13.1445259 49.992198,12.609375 47.8984375,12.609375 C46.1328037,12.609375 44.6171938,13.1132762 43.3515625,14.1210937 C42.0859312,15.1289113 41.1484405,16.6249901 40.5390625,18.609375 L40.5390625,36 L37.7265625,36 L37.7265625,10.640625 L40.421875,10.640625 Z M100.820312,23.578125 C100.820312,27.5312698 99.9453213,30.6679571 98.1953125,32.9882812 C96.4453037,35.3086054 94.0937648,36.46875 91.140625,36.46875 C87.6093573,36.46875 84.9375091,35.1562631 83.125,32.53125 L83.0078125,36 L80.3828125,36 L80.3828125,0 L83.171875,0 L83.171875,14.3203125 C84.9531339,11.5546737 87.5937325,10.171875 91.09375,10.171875 C94.093765,10.171875 96.464835,11.3163948 98.2070312,13.6054688 C99.9492275,15.8945427 100.820312,19.0781046 100.820312,23.15625 L100.820312,23.578125 Z M98.0078125,23.0859375 C98.0078125,19.7421708 97.3593815,17.1601654 96.0625,15.3398437 C94.7656185,13.5195221 92.9375118,12.609375 90.578125,12.609375 C88.781241,12.609375 87.2578188,13.0507768 86.0078125,13.9335937 C84.7578062,14.8164107 83.8125032,16.1093665 83.171875,17.8125 L83.171875,29.25 C84.578132,32.4375159 87.0624822,34.03125 90.625,34.03125 C92.9375116,34.03125 94.7460872,33.1171966 96.0507812,31.2890625 C97.3554753,29.4609284 98.0078125,26.7265807 98.0078125,23.0859375 Z M110.148438,36 L107.335938,36 L107.335938,0 L110.148438,0 L110.148438,36 Z M125.804688,31.96875 L133.210938,10.640625 L136.234375,10.640625 L125.40625,40.3125 L124.84375,41.625 C123.453118,44.7031404 121.304702,46.2421875 118.398438,46.2421875 C117.726559,46.2421875 117.007816,46.1328136 116.242188,45.9140625 L116.21875,43.59375 L117.671875,43.734375 C119.046882,43.734375 120.160152,43.3945346 121.011719,42.7148438 C121.863286,42.0351529 122.585935,40.8671958 123.179688,39.2109375 L124.421875,35.7890625 L114.859375,10.640625 L117.929688,10.640625 L125.804688,31.96875 Z"
                    id="nimbly" fill="#FFFFFF"></path>
            </g>
        </svg>
    </a>
</nav>

<script>
    document
        .getElementById("nb_nav_toggler")
        .addEventListener("click", () => {
            const instance = te.Sidenav.getInstance(
                document.getElementById("nb-bar")
            );
            instance.toggleSlim();
        });

    const nb_bar = document.getElementById("nb-bar");
    nb_bar.addEventListener("expanded.te.sidenav", (event) => {
        console.log(event);
        nb_api.post('[base-url]/api/v1/session', { "nb_bar_slim": false});
    });
    nb_bar.addEventListener("collapsed.te.sidenav", (event) => {
        console.log(event);
        nb_api.post('[base-url]/api/v1/session', { "nb_bar_slim": true});
    });
</script>