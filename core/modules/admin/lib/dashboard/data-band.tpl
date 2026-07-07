<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6">
    <h2 class="mb-3 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Your data#]</h2>
    <ul class="relative grid max-h-[220px] grid-cols-1 gap-2 overflow-y-auto text-sm sm:flex sm:flex-row sm:flex-wrap sm:gap-x-6 sm:gap-y-3">
        [#repeat data.user-resources tpl=data-resource-item#]
    </ul>
</section>
