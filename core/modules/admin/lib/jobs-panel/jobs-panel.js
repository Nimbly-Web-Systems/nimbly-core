document.addEventListener("alpine:init", () => {
    Alpine.data("jobs_panel", () => ({
        records: typeof _jobs_panel_records !== "undefined" ? _jobs_panel_records : [],
        search_term: "",
        status_filter: "",
        type_filter: "",
        attempts_filter: "",
        matches(record) {
            if (!record) {
                return true;
            }
            if (this.status_filter !== "" && record.status !== this.status_filter) {
                return false;
            }
            if (this.type_filter !== "" && record.type !== this.type_filter) {
                return false;
            }
            if (this.attempts_filter !== "" && String(record.attempts) !== this.attempts_filter) {
                return false;
            }
            const search = this.search_term.trim().toLowerCase();
            return search.length < 3
                || `${record.status} ${record.type} ${record.last_error}`.toLowerCase().includes(search);
        },
        statuses() {
            return [...new Set(this.records.map((record) => record.status))].sort();
        },
        types() {
            return [...new Set(this.records.map((record) => record.type))].sort();
        },
        attempt_counts() {
            return [...new Set(this.records.map((record) => record.attempts))].sort((a, b) => a - b);
        },
        filtered_count() {
            return this.records.filter((record) => this.matches(record)).length;
        },
        delete_job(uuid, el) {
            nb.api.delete(`${nb.base_url}/api/v1/.jobs/${uuid}`).then((data) => {
                if (data.success) {
                    nb.notify(nb.text.record_deleted);
                    el.closest("tr").remove();
                    this.records = this.records.filter((record) => record.uuid !== uuid);
                } else {
                    nb.notify(data.message);
                }
            });
        },
    }));
});
