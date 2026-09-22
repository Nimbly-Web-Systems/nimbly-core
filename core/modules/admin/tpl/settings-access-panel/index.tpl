<section class="rounded-2xl bg-neutral-50 p-5 shadow-md">
    <h2 class="font-semibold text-neutral-800">[#text Users & access#]</h2>
    <p class="mt-1 text-sm text-neutral-500">[#text Manage people first, then adjust roles when responsibilities change.#]</p>
    <div class="mt-4 flex flex-col gap-2">
        [#feature-cond features=view-users tpl=settings-users-link#]
        [#feature-cond features=view-roles tpl=settings-roles-link#]
    </div>
</section>
