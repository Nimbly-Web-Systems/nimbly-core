<section class="overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
    <div class="border-b border-neutral-200 p-5">
        <h2 class="text-lg font-semibold text-neutral-800">[#text Site#]</h2>
        <p class="mt-1 text-sm text-neutral-500">[#text Manage identity, languages, and the page templates available to editors.#]</p>
        <nav class="mt-5 flex gap-1 overflow-x-auto" aria-label="[#text Site settings#]">
            <a href="[#base-url#]/nb-admin/settings?section=general" [#if _ss.section=general echo="aria-current='page'"#] class="whitespace-nowrap rounded-lg px-3 py-2 text-sm [#if _ss.section=general echo='bg-neutral-200 font-semibold text-neutral-900' echo_else='text-neutral-600 hover:bg-neutral-100'#]">[#text General#]</a>
            <a href="[#base-url#]/nb-admin/settings?section=languages" [#if _ss.section=languages echo="aria-current='page'"#] class="whitespace-nowrap rounded-lg px-3 py-2 text-sm [#if _ss.section=languages echo='bg-neutral-200 font-semibold text-neutral-900' echo_else='text-neutral-600 hover:bg-neutral-100'#]">[#text Languages#]</a>
            <a href="[#base-url#]/nb-admin/settings?section=page-templates" [#if _ss.section=page-templates echo="aria-current='page'"#] class="whitespace-nowrap rounded-lg px-3 py-2 text-sm [#if _ss.section=page-templates echo='bg-neutral-200 font-semibold text-neutral-900' echo_else='text-neutral-600 hover:bg-neutral-100'#]">[#text Page templates#]</a>
        </nav>
    </div>
    <div class="p-5">[#_ss.content#]</div>
    <script>[#include file=[#base-path#]core/modules/admin/lib/site-settings/site-settings.js#]</script>
</section>
