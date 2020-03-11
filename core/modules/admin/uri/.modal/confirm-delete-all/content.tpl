<h3 class="modal-caption">Delete all</h3>
<p>
	Are you sure you want to delete all ((resource-name)) items?
</p>
<form action='[url url=".modal/confirm-delete-all"]' method="post" accept-charset="utf-8" id="modal_delete_all">
	[form-key modal_delete_all]
	<input type="hidden" name="resource" value="((resource-id))">
</form>
<div class='modal-buttons'>
	<button class="nb-button green" id="modal_delete_all" 
		data-submit="form#modal_delete_all"
		data-done='{
			"msg": "Deleted all ((resource-name)) items",
			"redirect": "((redirect-url))"
		}'>
		Yes, delete all ((resource-name)) items
	</button>
	<button class="nb-button nb-button-secondary" data-close-modal>Cancel</button>
</div>