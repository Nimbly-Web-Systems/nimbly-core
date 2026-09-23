<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
    <div class="min-w-0">[#feature-cond features=edit-.config tpl=settings-site-panel#]</div>
    <aside class="space-y-4" aria-label="[#text Settings sections#]">
        [#feature-cond features=view-users,view-roles tpl=settings-access-panel#]
        [#feature-cond features=view-.routes tpl=settings-routing-panel#]
        [#feature-cond features=edit-.navigation tpl=settings-navigation-panel#]
    </aside>
</div>
