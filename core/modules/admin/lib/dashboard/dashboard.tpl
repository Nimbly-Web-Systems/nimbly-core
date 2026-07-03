<div x-data="dashboard_status([#get _dash.failed_jobs echo#], [#_dash.has_recent_error#], [#_dash.low_disk#], [#_dash.can_pull_ext#], [#_dash.can_pull_core#])">
    [#get _dash.body echo#]
    <script>
        [#include file=[#base-path#]core/modules/admin/lib/dashboard/dashboard.js#]
    </script>
</div>
