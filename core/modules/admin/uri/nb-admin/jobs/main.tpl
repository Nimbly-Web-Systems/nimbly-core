<section class="bg-neutral-100 p-2 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Jobs#]</span>
    </nav>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-semibold text-neutral-800">[#text Jobs#]</h1>
            <h3 class="text-sm md:text-base pt-1 pb-2 text-neutral-700 font-medium">[#text Background job queue and scheduler activity.#]</h3>
        </div>
        <div class="flex gap-2 [#feature-cond manage-.jobs echo_else=hidden#]">
            <form action="[#url#]" method="post" accept-charset="utf-8">
                [#form-key run_jobs#]
                <button type="submit" class="[#btn-class-secondary#]">[#text Run due jobs now#]</button>
            </form>
            <form action="[#url#]" method="post" accept-charset="utf-8">
                [#form-key prune_jobs#]
                <button type="submit" class="[#btn-class-secondary#]">[#text Prune completed#]</button>
            </form>
        </div>
    </div>
</section>
<section class="bg-neutral-100 px-2 sm:px-4 md:px-6 lg:px-8 pb-10">
    [#jobs-panel#]
</section>
