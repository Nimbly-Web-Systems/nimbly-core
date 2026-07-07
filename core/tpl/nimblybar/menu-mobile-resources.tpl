<div x-cloak x-show="mobile_panel === 'resources'" x-transition id="nb_mobile_resources_menu"
    class="max-h-[min(70vh,30rem)] overflow-y-auto border-b border-white/10 px-2 py-3 md:hidden">
    <div class="mb-2 px-2 text-xs font-semibold uppercase tracking-wide text-white/60">[#text Resources#]</div>
    <ul class="m-0 flex list-none flex-col gap-1 p-0">
        [#get-user-resources#]
        [#repeat data.user-resources tpl=menu-mobile-resource-item#]
    </ul>
</div>
