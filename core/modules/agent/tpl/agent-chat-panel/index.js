function agent_chat_widget(nimblybar_side) {
    const url = nb.base_url + "/api/v1/agent-chat";
    const remembered = (() => {
        try { return JSON.parse(sessionStorage.getItem("agent_chat") || "{}"); } catch (error) { return {}; }
    })();
    return {
        side: nimblybar_side === "right" ? "left" : "right",
        open: Boolean(remembered.open),
        history: false,
        team: {},
        conversations: [],
        conversation: null,
        draft: "",
        busy: false,
        unread: 0,
        timer: null,
        get team_names() { return Object.values(this.team).join(", "); },
        init() {
            this.load_list().then(() => {
                if (remembered.uuid) this.open_conversation(remembered.uuid);
            });
            this.poll();
        },
        remember() {
            try {
                sessionStorage.setItem("agent_chat", JSON.stringify({ open: this.open, uuid: this.conversation?.uuid || "" }));
            } catch (error) {}
        },
        name(agent_id) { return this.team[agent_id] || agent_id; },
        toggle() {
            this.open = !this.open;
            this.remember();
            if (this.open) {
                // Someone (an agent) started a conversation: open it right away.
                const waiting = this.conversations.find(item => item.unread);
                if (!this.conversation && waiting) this.open_conversation(waiting.uuid);
                else this.conversation ? this.refresh() : this.load_list();
                this.$nextTick(() => this.$refs.input.focus());
            }
            this.poll();
        },
        toggle_history() {
            this.history = !this.history;
            if (this.history) this.load_list();
        },
        // Every 3 s while a conversation is open, otherwise a light unread check every 30 s.
        poll() {
            clearTimeout(this.timer);
            const active = this.open && this.conversation;
            this.timer = setTimeout(async () => {
                try { active ? await this.refresh() : await this.check_unread(); } finally { this.poll(); }
            }, active ? 3000 : 30000);
        },
        async check_unread() {
            const response = await nb.api.get(url + "?operation=unread");
            if (response.success && response.unread !== this.unread) await this.load_list();
        },
        async load_list() {
            const response = await nb.api.get(url + "?operation=list");
            if (!response.success) return;
            this.team = response.team;
            this.conversations = response.conversations;
            this.unread = this.conversations.reduce((sum, item) => sum + item.unread, 0);
        },
        // A new chat starts empty; the conversation is created with its first message.
        start() {
            this.conversation = null;
            this.history = false;
            this.remember();
            this.poll();
            this.$nextTick(() => this.$refs.input.focus());
        },
        async open_conversation(uuid) {
            const response = await nb.api.get(url + "?operation=get&uuid=" + encodeURIComponent(uuid));
            this.history = false;
            if (response.success) this.show(response);
        },
        async refresh() {
            if (!this.conversation) return;
            const response = await nb.api.get(url + "?operation=get&uuid=" + encodeURIComponent(this.conversation.uuid));
            if (response.success && this.conversation?.uuid === response.uuid) this.show(response);
        },
        show(view) {
            const before = this.conversation?.uuid === view.uuid ? this.conversation.messages.length : -1;
            const working = JSON.stringify(this.conversation?.working || []);
            this.team = view.team;
            this.conversation = view;
            this.remember();
            this.poll();
            if (before !== view.messages.length && this.open) {
                nb.api.post(url, { operation: "read", uuid: view.uuid }).then(() => this.load_list());
            }
            if (before !== view.messages.length || working !== JSON.stringify(view.working)) {
                this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight; });
            }
        },
        async send() {
            const text = this.draft.trim();
            if (!text || this.busy) return;
            this.busy = true;
            try {
                if (!this.conversation) {
                    const created = await nb.api.post(url, { operation: "create" });
                    if (!created.success) throw new Error(created.message);
                    this.conversation = created;
                }
                const response = await nb.api.post(url, { operation: "post", uuid: this.conversation.uuid, text });
                if (!response.success) throw new Error(response.message);
                this.draft = "";
                this.$refs.input.style.height = "auto";
                this.show(response);
            } catch (error) {
                nb.notify(error.message || "Message not sent");
            } finally {
                this.busy = false;
            }
        },
    };
}
