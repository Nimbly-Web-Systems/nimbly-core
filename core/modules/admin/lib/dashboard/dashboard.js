document.addEventListener("alpine:init", () => {
    Alpine.data("dashboard_attention", (failed_jobs, has_recent_error, low_disk) => ({
        busy: false,
        failed_jobs,
        has_recent_error,
        low_disk,
        site_updates: null,
        core_updates: null,
        get visible() {
            return this.failed_jobs > 0 || this.has_recent_error || this.low_disk
                || ((this.site_updates || 0) + (this.core_updates || 0)) > 0;
        },
        get_updates() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-status?dir=ext").then((data) => {
                this.site_updates = data.updates;
                this.busy = false;
            });
            nb.api.get(nb.base_url + "/api/v1/git-status").then((data) => {
                this.core_updates = data.updates;
                this.busy = false;
            });
        },
        pull_site() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-pull?dir=ext").then((data) => {
                this.site_updates = data.error ? this.site_updates : 0;
                this.busy = false;
            });
        },
        pull_core() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-pull").then((data) => {
                this.core_updates = data.error ? this.core_updates : 0;
                this.busy = false;
            });
        },
        init() {
            this.get_updates();
        },
    }));
});
