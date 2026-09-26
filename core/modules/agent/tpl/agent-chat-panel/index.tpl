<div id="agent-chat" data-confirm-delete="[#text Delete this chat?#]" x-data="agent_chat_widget('[#get data.config.site.nimblybar.side default=left#]')" x-cloak
    class="fixed bottom-20 z-50 md:bottom-4" :class="side === 'left' ? 'left-4' : 'right-4'">

    <section x-show="open" x-transition.opacity role="dialog" aria-label="[#text Talk with Nimbly#]"
        class="absolute bottom-16 flex h-[36rem] max-h-[calc(100vh-8rem)] w-[25rem] max-w-[calc(100vw-2rem)] flex-col overflow-hidden rounded-2xl border border-base-300 bg-base-100 shadow-xl"
        :class="side === 'left' ? 'left-0' : 'right-0'">

        <header class="flex h-14 shrink-0 items-center gap-1 px-3">
            <button type="button" x-show="history" class="btn btn-ghost btn-sm gap-1 px-2" @click="history = false" aria-label="[#text Back#]">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                <span class="text-sm font-semibold">[#text Chat history#]</span>
            </button>
            <button type="button" x-show="!history" class="btn btn-ghost btn-sm btn-circle ml-auto" @click="toggle_history()" aria-label="[#text Chat history#]">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12a9 9 0 1 0 3-6.7L3 8m0-5v5h5m4-1v5l3 3"/></svg>
            </button>
            <button type="button" class="btn btn-ghost btn-sm btn-circle" :class="history && 'ml-auto'" @click="toggle()" aria-label="[#text Close#]">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </header>

        <ul x-show="history" class="menu w-full flex-1 flex-nowrap overflow-y-auto overflow-x-hidden px-2 py-1">
            <li>
                <button type="button" class="font-medium" @click="start()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    [#text New chat#]
                </button>
            </li>
            <template x-for="item in conversations" :key="item.uuid">
                <li class="min-w-0">
                    <div class="!flex w-full min-w-0 items-start gap-1 !p-0">
                        <button type="button" class="flex min-w-0 flex-1 cursor-pointer items-start gap-2 px-3 py-1.5 text-left" @click="open_conversation(item.uuid)">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium" x-text="item.title"></span>
                                <span class="block truncate text-xs text-base-content/60" x-text="item.last"></span>
                            </span>
                            <span x-show="item.unread" class="badge badge-error badge-xs mt-1.5"></span>
                        </button>
                        <button type="button" class="btn btn-ghost btn-xs btn-square m-1.5 opacity-60 hover:opacity-100" @click="remove(item)" aria-label="[#text Delete chat#]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5 7l1 12a2 2 0 002 2h8a2 2 0 002-2l1-12M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/></svg>
                        </button>
                    </div>
                </li>
            </template>
        </ul>

        <div x-show="!history" x-ref="messages" class="flex flex-1 flex-col overflow-y-auto px-4 py-2">
            <h2 x-show="!conversation?.messages?.length" class="m-auto text-center text-xl font-semibold">[#text Talk with Nimbly#]</h2>
            <template x-for="message in conversation?.messages || []" :key="message.id">
                <div class="chat" :class="message.from === 'user' ? 'chat-end' : 'chat-start'">
                    <div x-show="message.from !== 'user'" class="chat-header text-xs text-base-content/60" x-text="name(message.from)"></div>
                    <div class="chat-bubble whitespace-pre-wrap break-words text-sm"
                        :class="message.from === 'user' ? 'chat-bubble-neutral' : 'bg-base-200 text-base-content'" x-text="message.text"></div>
                    <div x-show="message.link" class="chat-footer mt-1">
                        <a class="btn btn-sm btn-outline rounded-full" :href="nb.base_url + (message.link?.path || '')" x-text="message.link?.label"></a>
                    </div>
                </div>
            </template>
            <template x-for="work in conversation?.working || []" :key="work.agent">
                <div class="chat chat-start">
                    <div class="chat-header text-xs text-base-content/60" x-text="work.name"></div>
                    <div x-show="work.status === 'working'" class="chat-bubble bg-base-200 text-sm text-base-content">
                        <span class="loading loading-dots loading-xs"></span>
                        <template x-for="step in work.steps"><span class="block truncate text-xs text-base-content/60" x-text="step"></span></template>
                    </div>
                    <div x-show="work.status === 'failed'" class="chat-bubble chat-bubble-error text-sm">[#text Could not finish this one. Please try again.#]</div>
                </div>
            </template>
        </div>

        <div x-show="!history" class="p-3">
            <div class="flex items-end gap-2 rounded-3xl border border-base-300 bg-base-100 py-1.5 pl-4 pr-1.5 shadow-sm focus-within:border-base-content/40">
                <textarea x-model="draft" x-ref="input" rows="1" maxlength="8000" :placeholder="'[#text Message#] ' + (team.nimbly || team_names)"
                    @keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); send(); }"
                    @input="$el.style.height = 'auto'; $el.style.height = Math.min($el.scrollHeight, 160) + 'px'"
                    class="max-h-40 min-h-0 flex-1 resize-none self-center bg-transparent py-1 text-sm leading-5 outline-none"></textarea>
                <button type="button" class="btn btn-neutral btn-sm btn-circle" :disabled="busy || !draft.trim()" @click="send()" aria-label="[#text Send#]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19V5m-6 6l6-6 6 6"/></svg>
                </button>
            </div>
        </div>
    </section>

    <button type="button" class="btn btn-circle btn-primary btn-lg relative shadow-lg" @click="toggle()" aria-label="[#text Talk with Nimbly#]" :aria-expanded="open">
        <svg xmlns="http://www.w3.org/2000/svg" class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.9 9.9 0 01-4-.83L3 20l1.4-3.72A7.6 7.6 0 013 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        <span x-show="unread && !open" class="absolute right-0 top-0 flex size-3.5" aria-label="[#text New reply#]">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-error opacity-75"></span>
            <span class="relative inline-flex size-3.5 rounded-full border-2 border-base-100 bg-error"></span>
        </span>
    </button>
</div>

<script>
    [#include file=[#base-path#]core/modules/agent/tpl/agent-chat-panel/index.js#]
</script>
