<div x-cloak x-show="mobile_panel === 'resources'" x-transition id="nb_mobile_resources_menu"
    class="fixed bottom-16 left-0 right-0 z-[1095] max-h-[min(70vh,30rem)] overflow-y-auto border-b border-white/10 bg-cbar px-2 py-3 shadow-[0_-8px_20px_rgba(0,0,0,0.18)] md:hidden">
    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-white/60">[#text Resources#]</div>
    <ul class="m-0 flex list-none flex-col gap-1 p-0">
        [#get-user-resources#]
        [#repeat data.user-resources tpl=menu-mobile-resource-item#]
    </ul>
</div>
