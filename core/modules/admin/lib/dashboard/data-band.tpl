<section class="mb-6 rounded-2xl bg-neutral-50 p-6 shadow">
    <h2 class="mb-3 text-lg font-primary font-medium text-neutral-900">[#text Your data#]</h2>
    <ul class="relative flex flex-row flex-wrap gap-x-6 gap-y-3 overflow-y-auto max-h-[220px]">
        [#repeat data.user-resources tpl=data-resource-item#]
    </ul>
</section>
