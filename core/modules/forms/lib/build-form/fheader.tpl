<form class="mt-4 p-2 rounded-md" autocomplete="false" 
    x-data="[#_bf_name#]_form('[#_bf_resource#]')"
    @submit.prevent="submit" 
    [#if _bf_upload=(not-empty) echo="@nb_upload_ready.document='handle_upload_ready'"#]
>
    [#module forms api admin#]
    [#form-key registration#]
    [#honeypot-field#]

         


