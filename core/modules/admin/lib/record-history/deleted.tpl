<section class="bg-neutral-100 p-3 sm:p-4 md:p-6 lg:p-8 font-primary">
    <nav class="mb-2 flex items-center gap-1.5 text-xs font-medium text-neutral-500" aria-label="Breadcrumb">
        [#breadcrumb-home#]
        <span aria-hidden="true">/</span>
        <a class="hover:text-cnormal hover:underline" href="[#base-url#]/nb-admin/[#resource-id#]">[#resource-name [#resource-id#] plural#]</a>
        <span aria-hidden="true">/</span>
        <span class="text-neutral-700">[#text Deleted records#]</span>
    </nav>
    <div class="mb-4 flex flex-col items-stretch gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <h1 class="text-2xl font-semibold text-neutral-800 md:text-3xl">[#text Deleted records#]</h1>
        <a class="[#btn-class-secondary#] inline-flex min-h-11 items-center justify-center sm:min-h-0" href="[#base-url#]/nb-admin/[#resource-id#]">[#text Back to overview#]</a>
    </div>
    <form action="[#base-url#]/nb-admin/history?resource=[#resource-id#]" method="post" accept-charset="utf-8" class="rounded-box border border-base-300 bg-base-100 p-2 sm:p-3">
        [#form-key restore_record#]
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">[#text Record#]</th>
                        <th scope="col">[#text Deleted#]</th>
                        <th scope="col">[#text Who#]</th>
                        <th scope="col"><span class="sr-only">[#text Actions#]</span></th>
                    </tr>
                </thead>
                <tbody>
                    [#_rh.rows#]
                </tbody>
            </table>
        </div>
    </form>
</section>
