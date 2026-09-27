function agent_chat_widget(nimblybar_side) {
    const url = nb.base_url + "/api/v1/agent-chat";
    const remembered = (() => {
        try { return JSON.parse(sessionStorage.getItem("agent_chat") || "{}"); } catch (error) { return {}; }
    })();
    return {
        side: nimblybar_side === "right" ? "left" : "right",
        // A phone starts every page with the chat closed, so a full-screen chat never traps anyone.
        open: Boolean(remembered.open) && !window.matchMedia("(max-width: 767px)").matches,
        history: false,
        team: {},
        conversations: [],
        conversation: null,
        draft: "",
        busy: false,
        unread: 0,
        waiting: false,
        timer: null,
        delay: 2000,
        phone: false,
        visible: null,
        fit: null,
        get team_names() { return Object.values(this.team).join(", "); },
        init() {
            // On a phone the chat fills the part of the screen the keyboard leaves free.
            const phone = window.matchMedia("(max-width: 767px)");
            this.phone = phone.matches;
            phone.addEventListener("change", (event) => { this.phone = event.matches; this.lock_page(); });
            const viewport = window.visualViewport;
            if (viewport) {
                this.fit = () => {
                    // Only follow the visible area while the keyboard takes part of the screen.
                    const keyboard = window.innerHeight - viewport.height > 80;
                    this.visible = keyboard ? { top: Math.max(0, viewport.offsetTop), height: viewport.height } : null;
                    if (this.open && this.phone) this.$nextTick(() => { this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight; });
                };
                viewport.addEventListener("resize", this.fit);
                viewport.addEventListener("scroll", this.fit);
                this.fit();
            }
            this.lock_page();
            // A closed chat starts slow; activity speeds it up.
            this.delay = this.open ? 2000 : 64000;
            document.addEventListener("visibilitychange", () => {
                if (document.hidden) return clearTimeout(this.timer);
                this.delay = 2000;
                this.check();
            });
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
        panel_style() {
            return this.phone && this.visible ? `top: ${this.visible.top}px; height: ${this.visible.height}px` : "";
        },
        lock_page() {
            document.documentElement.classList.toggle("overflow-hidden", this.open && this.phone);
            document.body.classList.toggle("overflow-hidden", this.open && this.phone);
        },
        name(agent_id) { return this.team[agent_id] || agent_id; },
        // Agents mark names like **Add project** in bold; everything else stays plain text.
        format(text) {
            const escaped = String(text || "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
            return escaped.replace(/\*\*(\S(?:.*?\S)?)\*\*/g, "<strong>$1</strong>");
        },
        toggle() {
            this.open = !this.open;
            this.remember();
            this.lock_page();
            if (this.open) {
                this.delay = 2000;
                this.fit?.();
                // Someone (an agent) started a conversation: open it right away.
                const waiting = this.conversations.find(item => item.unread);
                if (!this.conversation && waiting) this.open_conversation(waiting.uuid);
                else this.conversation ? this.refresh() : this.load_list();
                // On a phone the keyboard waits until the input is tapped.
                if (!this.phone) this.$nextTick(() => this.$refs.input.focus());
            }
            this.poll();
        },
        toggle_history() {
            this.history = !this.history;
            if (this.history) this.load_list();
        },
        // Every 2 s after activity or while an agent is answering, doubling up to 64 s while nothing changes.
        // A hidden browser tab does not check at all.
        poll() {
            clearTimeout(this.timer);
            if (document.hidden) return;
            if (this.waiting) this.delay = 2000;
            this.timer = setTimeout(() => {
                this.delay = Math.min(this.delay * 2, 64000);
                this.check();
            }, this.delay);
        },
        async check() {
            try { this.open && this.conversation ? await this.refresh() : await this.check_unread(); } finally { this.poll(); }
        },
        async check_unread() {
            const response = await nb.api.get(url + "?operation=unread");
            if (!response.success) return;
            this.waiting = response.waiting;
            if (response.unread !== this.unread) {
                this.delay = 2000;
                await this.load_list();
            }
        },
        async load_list() {
            const response = await nb.api.get(url + "?operation=list");
            if (!response.success) return;
            this.team = response.team;
            this.conversations = response.conversations;
            this.unread = this.conversations.reduce((sum, item) => sum + item.unread, 0);
            this.waiting = this.conversations.some(item => item.waiting);
        },
        // A new chat starts empty; the conversation is created with its first message.
        start() {
            this.conversation = null;
            this.history = false;
            this.delay = 2000;
            this.remember();
            this.poll();
            this.$nextTick(() => this.$refs.input.focus());
        },
        async remove(item) {
            if (!window.confirm(this.$el.closest("#agent-chat").dataset.confirmDelete)) return;
            const response = await nb.api.post(url, { operation: "delete", uuid: item.uuid });
            if (!response.success) return;
            if (this.conversation?.uuid === item.uuid) this.conversation = null;
            this.remember();
            this.load_list();
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
            // A reply that arrived while you were here and asks to open a page: go there (the chat stays open).
            if (before >= 0) {
                const opener = view.messages.slice(before).find(message => message.link?.open && message.from !== "user");
                if (opener) {
                    this.conversation = view;
                    this.remember();
                    window.location.href = nb.base_url + opener.link.path;
                    return;
                }
            }
            const working = JSON.stringify(this.conversation?.working || []);
            this.team = view.team;
            this.conversation = view;
            if (view.working.some(work => work.status === "working")) this.waiting = true;
            if (before !== view.messages.length || working !== JSON.stringify(view.working)) this.delay = 2000;
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
                const response = await nb.api.post(url, { operation: "post", uuid: this.conversation?.uuid || "", text });
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
