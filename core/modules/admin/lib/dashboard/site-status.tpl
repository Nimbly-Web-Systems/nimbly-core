<section class="mb-6 rounded-2xl bg-neutral-50 p-6 shadow">
    <h2 class="mb-3 text-lg font-primary font-medium text-neutral-900">[#text Site status#]</h2>
    <ul class="flex flex-row flex-wrap gap-x-8 gap-y-3">
        [#get _dash.status_items echo#]
    </ul>
</section>
