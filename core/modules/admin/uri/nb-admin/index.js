Alpine.data('updates', () => ({
    busy: false,
	site_updates: null,
    core_updates:null,
    async get_updates() {
        this.busy = true;
        nb.api.get('api/v1/git-status?dir=ext').then(data => {
            this.site_updates = data.updates;
            this.busy = false;
        });
        nb.api.get('api/v1/git-status').then(data => {
            this.core_updates = data.updates;
            this.busy = false;
        });
    },
    async pull_site() {
        this.busy = true;
        nb.api.get('api/v1/git-pull?dir=ext').then(data => {
            this.site_updates = data.error? this.site_updates : 0;
            this.busy = false;
        });
    },
    async pull_core() {
        this.busy = true;
        nb.api.get('api/v1/git-status').then(data => {
            this.core_updates = data.error? this.core_updates : 0;
            this.busy = false;
        });
    },
    init() {
        this.get_updates();
    }
}));
