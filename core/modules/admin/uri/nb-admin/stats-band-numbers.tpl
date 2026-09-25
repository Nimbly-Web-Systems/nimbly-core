<ul class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex lg:flex-row lg:gap-x-8">
    <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Visits#]</div>
        <div class="text-xl font-semibold text-neutral-500">[#get _dash.stats_visits#]</div>
        <div class="text-xs text-neutral-500">[#text [#get _dash.stats_visits_change#]#]</div>
    </li>
    <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Pageviews#]</div>
        <div class="text-xl font-semibold text-neutral-500">[#get _dash.stats_pageviews#]</div>
        <div class="text-xs text-neutral-500">[#text [#get _dash.stats_pageviews_change#]#]</div>
    </li>
    <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
        <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Bots & scanners#]</div>
        <div class="text-xl font-semibold text-neutral-500">[#get _dash.stats_bots#]</div>
        <div class="text-xs text-neutral-500">[#get _dash.stats_scanners#] [#text scanner requests#]</div>
    </li>
</ul>
<div class="mt-5 flex h-24 items-end gap-px border-b border-neutral-200 sm:gap-0.5" role="img" aria-label="[#text Pageviews per day#]">
    [#repeat _dash.stats_days tpl=stats-day-bar#]
</div>
<div class="mt-1 flex justify-between text-xs text-neutral-500">
    <span>[#get _dash.stats_first_day#]</span>
    <span>[#text Today#]</span>
</div>
