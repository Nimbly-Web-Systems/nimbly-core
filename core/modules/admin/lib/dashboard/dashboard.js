document.addEventListener("alpine:init", () => {
    /* Groups UTC hourly counts into the viewer's local days. */
    Alpine.data("dashboard_stats", (hours) => ({
        current: {},
        previous: {},
        bars: [],
        first_label: "",
        init() {
            const local_date = new Intl.DateTimeFormat("en-CA", { year: "numeric", month: "2-digit", day: "2-digit" });
            const per_day = {};
            for (const [key, counts] of Object.entries(hours || {})) {
                const day = local_date.format(new Date(key + ":00:00Z"));
                per_day[day] ??= {};
                for (const field in counts) {
                    per_day[day][field] = (per_day[day][field] || 0) + counts[field];
                }
            }
            const dates = [];
            const cursor = new Date();
            cursor.setHours(12, 0, 0, 0);
            for (let i = 0; i < 60; i++) {
                dates.unshift(local_date.format(cursor));
                cursor.setDate(cursor.getDate() - 1);
            }
            const sum = (list) => list.reduce((total, day) => {
                for (const field in total) {
                    total[field] += per_day[day]?.[field] || 0;
                }
                return total;
            }, { visitors: 0, pageviews: 0, bots: 0, scanners: 0 });
            const recent = dates.slice(30);
            this.previous = sum(dates.slice(0, 30));
            this.current = sum(recent);
            const today = recent[recent.length - 1];
            const max = Math.max(1, ...recent.map((day) => per_day[day]?.pageviews || 0));
            const label = new Intl.DateTimeFormat(undefined, { weekday: "short", day: "numeric", month: "short" });
            this.bars = recent.map((day) => {
                const views = per_day[day]?.pageviews || 0;
                return {
                    date: day,
                    height: views ? Math.max(3, Math.round(views / max * 100)) : 0,
                    today: day === today ? 1 : 0,
                    title: label.format(new Date(day + "T12:00:00")) + ": " + this.format(views) + " pageviews, "
                        + this.format(per_day[day]?.visitors || 0) + " visits" + (day === today ? " (so far)" : ""),
                };
            });
            this.first_label = new Intl.DateTimeFormat(undefined, { day: "numeric", month: "short" })
                .format(new Date(recent[0] + "T12:00:00"));
        },
        format(number) {
            return new Intl.NumberFormat().format(number || 0);
        },
        change(field) {
            const before = this.previous[field];
            if (!before) {
                return "No earlier period yet";
            }
            const change = Math.round((this.current[field] - before) / before * 100);
            return (change > 0 ? "+" : "") + change + "% vs previous 30 days";
        },
    }));

    Alpine.data("dashboard_status", (failed_jobs, has_recent_error, low_disk, can_pull_ext, can_pull_core, maintenance_unhealthy = false, assets_stale = false) => ({
        busy: false,
        failed_jobs,
        maintenance_unhealthy,
        has_recent_error,
        low_disk,
        assets_stale,
        can_pull_ext,
        can_pull_core,
        site_updates: null,
        core_updates: null,
        site_updated_label: null,
        core_updated_label: null,
        get attention_visible() {
            return this.maintenance_unhealthy || this.failed_jobs > 0 || this.has_recent_error || this.low_disk || this.assets_stale;
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
                this.site_updated_label = data.error ? this.site_updated_label : "now";
                this.assets_stale = data.assets_stale ?? this.assets_stale;
            }).finally(() => {
                this.busy = false;
            });
        },
        pull_core() {
            this.busy = true;
            nb.api.get(nb.base_url + "/api/v1/git-pull").then((data) => {
                this.core_updates = data.error ? this.core_updates : 0;
                this.core_updated_label = data.error ? this.core_updated_label : "now";
                this.assets_stale = data.assets_stale ?? this.assets_stale;
            }).finally(() => {
                this.busy = false;
            });
        },
        init() {
            this.get_updates();
        },
    }));
});
