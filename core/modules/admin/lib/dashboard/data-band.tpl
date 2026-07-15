<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6">
    <h2 class="mb-3 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Your data#]</h2>
    <ul class="relative flex max-h-[260px] flex-wrap items-start justify-start gap-x-12 gap-y-7 overflow-y-auto text-sm">
        [#repeat data.user-resources tpl=data-resource-item#]
    </ul>
</section>
