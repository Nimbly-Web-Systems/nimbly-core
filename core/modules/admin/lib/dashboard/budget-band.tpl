<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6">
    <h2 class="mb-3 flex flex-wrap items-center gap-2 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Hour budget#] <span class="text-sm font-normal text-neutral-500">· [#text since#] [#fmt var=_dash.budget_since type=date fmt=medium#]</span> [#if _dash.budget_hidden=true tpl=budget-band-hidden#]</h2>
    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex lg:flex-row lg:gap-x-8">
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Available#]</div>
            <div class="text-xl font-semibold text-neutral-500">[#fmt var=_dash.budget_available hours#]</div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Used#]</div>
            <div class="text-xl font-semibold text-neutral-500">[#fmt var=_dash.budget_used hours#]</div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Left#]</div>
            <div class="text-xl font-semibold text-neutral-500">[#fmt var=_dash.budget_left hours#]</div>
        </li>
    </ul>
    <progress class="progress progress-primary mt-5 w-full" value="[#get _dash.budget_used#]" max="[#get _dash.budget_bar_max#]"></progress>
    <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-600">[#get _dash.budget_categories echo#]</ul>
    [#if _dash.budget_has_history=true tpl=budget-band-history#]
</section>
