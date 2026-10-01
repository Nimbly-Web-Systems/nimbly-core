<section class="mb-3 rounded-lg bg-neutral-50 p-3 shadow sm:mb-6 sm:p-6"
    x-data='{ budget: [#fmt var=_dash.budget empty={} json#], labels: { content: "[#text Content#]", design: "[#text Design#]", development: "[#text Development#]", maintenance: "[#text Technical maintenance#]", documentation: "[#text Documentation#]", communication: "[#text Communication#]" }, hours(value) { return Number(value || 0).toLocaleString(undefined, { maximumFractionDigits: 1 }) + " [#text h#]"; } }'>
    <h2 class="mb-3 text-base font-primary font-medium text-neutral-900 sm:text-lg">[#text Hour budget#]</h2>
    <ul class="grid grid-cols-1 gap-3 sm:grid-cols-3 lg:flex lg:flex-row lg:gap-x-8">
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Bought#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="hours(budget.bought)"></div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Used#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="hours(budget.used)"></div>
        </li>
        <li class="min-w-0 rounded-lg border border-neutral-200 p-3 sm:min-w-[150px] sm:border-0 sm:p-0">
            <div class="text-xs font-semibold uppercase tracking-wide text-neutral-900">[#text Left#]</div>
            <div class="text-xl font-semibold text-neutral-500" x-text="hours(budget.left)"></div>
        </li>
    </ul>
    <progress class="progress progress-primary mt-5 w-full" :value="budget.used" :max="Math.max(budget.bought, budget.used)"></progress>
    <ul class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-neutral-600">
        <template x-for="(value, key) in budget.by_category" :key="key">
            <li><span x-text="labels[key] || key"></span> <span class="font-medium text-neutral-900" x-text="hours(value)"></span></li>
        </template>
    </ul>
</section>
