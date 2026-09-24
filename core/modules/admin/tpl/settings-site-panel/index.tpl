<section class="overflow-hidden rounded-2xl bg-neutral-50 shadow-md">
    <div class="border-b border-neutral-200 p-5">
        <nav class="tabs tabs-lift w-fit max-w-full" role="tablist" aria-label="[#text Site settings#]">
            <a role="tab" href="[#base-url#]/nb-admin/settings?section=general" aria-selected="[#if _ss.section=general echo=true echo_else=false#]" [#if _ss.section=general echo="aria-current='page' class='tab tab-active whitespace-nowrap font-semibold'" echo_else="class='tab whitespace-nowrap'"#]>[#text General#]</a>
            <a role="tab" href="[#base-url#]/nb-admin/settings?section=languages" aria-selected="[#if _ss.section=languages echo=true echo_else=false#]" [#if _ss.section=languages echo="aria-current='page' class='tab tab-active whitespace-nowrap font-semibold'" echo_else="class='tab whitespace-nowrap'"#]>[#text Languages#]</a>
        </nav>
    </div>
    <div class="p-5">[#_ss.content#]</div>
    <script>[#include file=[#base-path#]core/modules/admin/lib/site-settings/site-settings.js#]</script>
</section>
