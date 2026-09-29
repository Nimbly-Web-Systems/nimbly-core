<button type="button" class="nb-page-action btn btn-sm join-item gap-1.5" data-nb-page-edit aria-pressed="false">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
    </svg>
    <span>[#text Edit#]</span>
</button>
<dialog id="nb-modal-leave-edit" class="modal">
    <div class="modal-box">
        <h3 class="text-lg font-bold">[#text Unsaved changes#]</h3>
        <p class="py-4">[#text Save your changes before you stop editing?#]</p>
        <div class="modal-action">
            <button type="button" class="btn btn-ghost" data-nb-leave-edit="keep">[#text Keep editing#]</button>
            <button type="button" class="btn" data-nb-leave-edit="discard">[#text Discard#]</button>
            <button type="button" class="btn btn-primary" data-nb-leave-edit="save">[#text Save#]</button>
        </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>[#text Close#]</button></form>
</dialog>
