document.addEventListener("alpine:init", () => {
    Alpine.data("jobs_panel", () => ({
        delete_job(uuid, el) {
            nb.api.delete(`${nb.base_url}/api/v1/.jobs/${uuid}`).then((data) => {
                if (data.success) {
                    nb.notify(nb.text.record_deleted);
                    el.closest("tr").remove();
                } else {
                    nb.notify(data.message);
                }
            });
        },
    }));
});
