

    <form autocomplete="false" data-edit-uuid="[content-uuid]" class="nb-form mt-2">

        <label>Titel in browser
            <input type="text" name="page_title" placeholder="" value="[get page_settings.page_title]" />
        </label>

        [set page-settings-fields=]
        [page-settings-fields]

        <div class="flex flex-shrink-0 flex-wrap items-center p-4 border-t border-solid border-neutral-100 -mx-4 mt-4">
        
        <button type="button" class="px-6
        py-2.5
        bg-cdark
        text-white
        font-medium
        text-xs
        leading-tight
        uppercase
        rounded
        shadow-md
        hover:bg-cdarkest hover:shadow-lg
        focus:bg-cdarkest focus:shadow-lg focus:outline-none focus:ring-0
        active:bg-cdarkest active:shadow-lg
        transition
        duration-150
        ease-in-out" data-put=".config/[content-uuid]"
                data-done='{"redirect": "[url relative] ", "msg": "Pagina instellingen zijn opgeslagen"}'>Opslaan</button>

                
        <button type="button" class="
                ml-2
                px-6
                py-2.5
                bg-white
        border
        border-solid
        border-cnormal
        text-gray-600
                font-medium
                text-xs
                leading-tight
                uppercase
                rounded
                shadow-md
                hover:border-cdark hover:shadow-lg
                focus:border-cdark focus:shadow-lg focus:outline-none focus:ring-0
                active:border-cdarkest active:shadow-lg
                transition
                duration-150
                ease-in-out" data-te-modal-dismiss>Sluiten</button>

        <a href="[base-url]/admin" class="px-6 cursor-pointer
        ml-2
        py-2.5
        bg-white
        border
        border-solid
        border-cnormal
        text-gray-600
        font-medium
        text-xs
        leading-tight
        uppercase
        rounded
        shadow-md
        hover:border-cdark hover:shadow-lg
        focus:border-cdark focus:shadow-lg focus:outline-none focus:ring-0
        active:border-cdarkest active:shadow-lg
        transition
        duration-150
        ease-in-out
       ">Naar Dashboard</a>
    </div>



    </form>