<section class="overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
    <div class="border-b border-neutral-200 p-5">
        <h2 class="text-lg font-semibold text-neutral-800">[#text Site#]</h2>
        <p class="mt-1 text-sm text-neutral-500">[#text Manage identity, languages, and custom pages available to editors.#]</p>
        <nav class="tabs tabs-lift mt-5 w-fit max-w-full flex-nowrap overflow-x-auto" role="tablist" aria-label="[#text Site settings#]">
            <a role="tab" href="[#base-url#]/nb-admin/settings?section=general" aria-selected="[#if _ss.section=general echo=true echo_else=false#]" [#if _ss.section=general echo="aria-current='page' class='tab tab-active whitespace-nowrap font-semibold'" echo_else="class='tab whitespace-nowrap'"#]>[#text General#]</a>
            <a role="tab" href="[#base-url#]/nb-admin/settings?section=languages" aria-selected="[#if _ss.section=languages echo=true echo_else=false#]" [#if _ss.section=languages echo="aria-current='page' class='tab tab-active whitespace-nowrap font-semibold'" echo_else="class='tab whitespace-nowrap'"#]>[#text Languages#]</a>
            <a role="tab" href="[#base-url#]/nb-admin/settings?section=page-templates" aria-selected="[#if _ss.section=page-templates echo=true echo_else=false#]" [#if _ss.section=page-templates echo="aria-current='page' class='tab tab-active whitespace-nowrap font-semibold'" echo_else="class='tab whitespace-nowrap'"#]>[#text Custom pages#]</a>
        </nav>
    </div>
    <div class="p-5">[#_ss.content#]</div>
    <script>[#include file=[#base-path#]core/modules/admin/lib/site-settings/site-settings.js#]</script>
</section>
