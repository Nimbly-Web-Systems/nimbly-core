<section x-show="attention_visible" x-cloak
    class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5">
    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-amber-800">[#text Needs attention#]</h2>
    <ul class="space-y-3 text-sm text-amber-900">
        <li x-show="failed_jobs > 0" x-cloak>
            <div class="font-medium" x-text="failed_jobs === 1 ? '1 job failed' : failed_jobs + ' jobs failed'"></div>
            <a href="[#base-url#]/nb-admin/jobs" class="cursor-pointer mt-1.5 inline-flex items-center rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">[#text Review#]</a>
        </li>
        <li x-show="has_recent_error" x-cloak>
            <div class="font-medium">[#text A system error was recorded in the last 24 hours#]</div>
            <a href="[#base-url#]/nb-admin/syslog" class="cursor-pointer mt-1.5 inline-flex items-center rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">[#text Review log#]</a>
        </li>
        <li x-show="low_disk" x-cloak>
            <div class="font-medium">[#text Disk space is running low#]</div>
            <a href="[#base-url#]/nb-admin/debug" class="cursor-pointer mt-1.5 inline-flex items-center rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-medium text-amber-800 hover:bg-amber-100">[#text View#]</a>
        </li>
    </ul>
</section>
