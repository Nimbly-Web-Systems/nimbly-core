<section x-show="attention_visible" x-cloak
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
    </ul>
</section>
