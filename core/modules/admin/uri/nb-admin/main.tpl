<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Dashboard#]</span>
    </nav>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800">[#text Dashboard#]</h1>
            <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700">[#text dashboard-subtitle#]</h3>
        </div>
        [#feature-cond features=edit-.config tpl=dashboard-settings-btn#]
    </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    [#dashboard#]
</section>
