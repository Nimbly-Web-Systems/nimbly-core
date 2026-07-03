document.addEventListener("alpine:init", () => {
    Alpine.data("resource_switcher_live", (resource, title_field) => ({
        resource,
        title_field,
        query: "",
        results: [],
        search() {
            const q = this.query.trim();
            if (q.length < 2) {
                this.results = [];
                return;
            }
            nb.api.get(nb.base_url + "/api/v1/" + this.resource + "?search=" + encodeURIComponent(q) + "&limit=10")
                .then((data) => {
                    if (!data.success) {
                        this.results = [];
                        return;
                    }
                    const records = data[this.resource] || {};
                    this.results = Object.keys(records).map((uuid) => ({
                        uuid,
                        title: (this.title_field && records[uuid][this.title_field]) || uuid,
                        url: nb.base_url + "/nb-admin/" + this.resource + "/" + uuid,
                    }));
                });
        },
    }));
});
