<aside class="rounded-md border border-warning/40 bg-warning/10 p-4 text-sm text-neutral-700">
    <h3 class="font-semibold">[#text Used in menus#]</h3>
    <p class="mt-1">[#text This page is in the menus below. If you delete it, its links disappear from the site.#]</p>
    <ul class="mt-2 list-disc pl-5">
        [#repeat managed_page_navigation_references tpl=managed-page-navigation-reference var=reference#]
    </ul>
</aside>
