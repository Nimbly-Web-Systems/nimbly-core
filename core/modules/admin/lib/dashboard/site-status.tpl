<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6">
    <h2 class="mb-3 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Site status#]</h2>
    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:flex lg:flex-row lg:flex-wrap lg:gap-x-8 lg:gap-y-3">
        [#get _dash.status_items echo#]
    </ul>
</section>
