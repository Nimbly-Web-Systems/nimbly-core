<div x-data="jobs_panel()">
    <p class="mb-3 text-sm text-neutral-500">[#_jp.counts#]</p>
    [#_jp.body#]
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/jobs-panel/jobs-panel.js#]
    </script>
</div>
