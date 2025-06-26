<tr>
    <td>
        [#record.name#]
    </td>
    <td>
        [#record.env#]
    </td>
    <td>
        <div class="flex items-center">
            <form action="[#url#]" method="post" accept-charset="utf-8">
                [#form-key install_module#]
                <input type="hidden" name="module_name" value="[#record.name#]">
                <input type="hidden" name="module_path" value="[#record.path#]">
                <button type="submit" class="[#btn-class-secondary#]">Install</button>
            </form>
        </div>
    </td>
</tr>