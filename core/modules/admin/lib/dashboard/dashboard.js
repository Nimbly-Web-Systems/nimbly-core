document.addEventListener("alpine:init", () => {
    Alpine.data("dashboard_status", (failed_jobs, has_recent_error, low_disk, can_pull_ext, can_pull_core) => ({
        busy: false,
        failed_jobs,
        has_recent_error,
        low_disk,
        can_pull_ext,
        can_pull_core,
        site_updates: null,
        core_updates: null,
        get attention_visible() {
            return this.failed_jobs > 0 || this.has_recent_error || this.low_disk;
        },
        get_updates() {
            if (!this.can_pull_ext && !this.can_pull_core) {
                return;
            }
            this.busy = true;
            const checks = [];
            if (this.can_pull_ext) {
                checks.push(nb.api.get(nb.base_url + "/api/v1/git-status?dir=ext").then((data) => {
                    this.site_updates = data.updates;
                }));
            }
            if (this.can_pull_core) {
                checks.push(nb.api.get(nb.base_url + "/api/v1/git-status").then((data) => {
                    this.core_updates = data.updates;
                }));
            }
            Promise.all(checks).finally(() => {
                this.busy = false;
            });
        },
        pull_site() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-pull?dir=ext").then((data) => {
                this.site_updates = data.error ? this.site_updates : 0;
            }).finally(() => {
                this.busy = false;
            });
        },
        pull_core() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-pull").then((data) => {
                this.core_updates = data.error ? this.core_updates : 0;
            }).finally(() => {
                this.busy = false;
            });
        },
        init() {
            this.get_updates();
        },
    }));
});
