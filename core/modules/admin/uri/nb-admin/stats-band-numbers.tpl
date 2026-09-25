<div x-data='dashboard_stats([#fmt var=_dash.stats_hours empty={} json#])'>
    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex lg:flex-row lg:gap-x-8">
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Visits#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="format(current.visitors)"></div>
            <div class="text-xs text-neutral-500" x-text="change('visitors')"></div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Pageviews#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="format(current.pageviews)"></div>
            <div class="text-xs text-neutral-500" x-text="change('pageviews')"></div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Bots & scanners#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="format(current.bots + current.scanners)"></div>
            <div class="text-xs text-neutral-500"><span x-text="format(current.scanners)"></span> [#text scanner requests#]</div>
        </li>
    </ul>
    <div class="mt-5 flex h-24 items-end gap-px border-b border-neutral-200 sm:gap-0.5" role="img" aria-label="[#text Pageviews per day#]">
        <template x-for="bar in bars" :key="bar.date">
            <div class="min-w-0 flex-1 rounded-t-sm bg-primary data-[today=1]:opacity-40" :style="'height: ' + bar.height + '%'" :data-today="bar.today" :title="bar.title"></div>
        </template>
    </div>
    <div class="mt-1 flex justify-between text-xs text-neutral-500">
        <span x-text="first_label"></span>
        <span>[#text Today#]</span>
    </div>
</div>
