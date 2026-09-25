<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6">
    <h2 class="mb-3 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Visits#] <span class="text-sm font-normal text-neutral-500">· [#text last 30 days#]</span></h2>
    [#if _dash.stats_has_data=false tpl=stats-band-empty#]
    [#if _dash.stats_has_data=true tpl=stats-band-numbers#]
</section>
