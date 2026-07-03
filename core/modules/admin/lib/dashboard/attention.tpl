<section x-data="dashboard_attention([#get _dash.failed_jobs echo#], [#_dash.has_recent_error#], [#_dash.low_disk#])"
    x-show="visible" x-cloak
    class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-800">[#text Needs attention#]</h2>
    <ul class="space-y-2 text-sm text-amber-900">
        <li x-show="failed_jobs > 0" x-cloak>
            <a href="[#base-url#]/nb-admin/jobs" class="font-medium hover:underline">
                <span x-text="failed_jobs === 1 ? '1 job failed' : failed_jobs + ' jobs failed'"></span> &mdash; [#text review#]
            </a>
        </li>
        <li x-show="has_recent_error" x-cloak>
            <a href="[#base-url#]/nb-admin/syslog" class="font-medium hover:underline">[#text A system error was recorded in the last 24 hours &mdash; review the log#]</a>
        </li>
        <li x-show="low_disk" x-cloak>
            <a href="[#base-url#]/nb-admin/debug" class="font-medium hover:underline">[#text Disk space is running low#]</a>
        </li>
        <li x-show="site_updates > 0" x-cloak>
            [#text Site updates available:#] <span class="font-medium" x-text="site_updates"></span>
            <button type="button" class="ml-2 font-medium underline disabled:opacity-50" @click="pull_site" :disabled="busy">[#text Update now#]</button>
        </li>
        <li x-show="core_updates > 0" x-cloak>
            [#text Core updates available:#] <span class="font-medium" x-text="core_updates"></span>
            <button type="button" class="ml-2 font-medium underline disabled:opacity-50" @click="pull_core" :disabled="busy">[#text Update now#]</button>
        </li>
    </ul>
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/dashboard/dashboard.js#]
    </script>
</section>
