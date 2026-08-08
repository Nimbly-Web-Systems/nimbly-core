<form class="[#_bf_form_class#] [#_bf_form_visual_class#]" autocomplete="false"
    x-data="[#_bf_js_name#]_form('[#_bf_resource#]', '[#get _bf_uuid#]')"
    [#if _bf_uuid=(not-empty) echo=":data-lang='lang' @input='sync_ai_input($event, lang)' @nb:editor-change='sync_ai_editor($event, lang)'"#]
    @submit.prevent="submit"
    [#if _bf_upload=(not-empty) echo="@nb_upload_ready.document='handle_upload_ready'"#]
>
    [#form-key [#_bf_name#]#]
    [#if _bf_honeypot=(not-empty) tpl=honeypot-field#]

         
