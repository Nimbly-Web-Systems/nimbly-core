<button class="[#btn-class-secondary#]" type="button" onclick="nb_delete_all_modal.showModal()">
	[#text Delete all files#]
</button>
<dialog id="nb_delete_all_modal" class="modal">
	<div class="modal-box border border-cnormal bg-clight">
		<h3 class="font-bold text-lg">[#text Delete all files#]</h3>
		<p class="py-4">[#text Delete all files? Are you sure?#]</p>
		<div class="modal-action">
			<form method="dialog">
				<button class="[#btn-class-secondary#]" type="submit">[#text Cancel#]</button>
			</form>
			<button class="[#btn-class-primary#]" type="button" id="nb_btn_delete_all">[#text Delete all#]</button>
		</div>
	</div>
	<form method="dialog" class="modal-backdrop">
		<button>[#text Close#]</button>
	</form>
</dialog>
<script>
	const nb_btn_delete_all = document.getElementById('nb_btn_delete_all');
	nb_btn_delete_all.addEventListener('click', (event) => {
		if (event.target != nb_btn_delete_all) {
			return;
		}
		nb.api.get("[#base_url#]/api/v1/empty-resource?resource=.files").then((data) => {
			if (data.success) {
				nb.api.post('[#base_url#]/api/v1/.system-messages', { message: "[#text Deleted all files#]"}).then((data) =>
				{ window.location.reload(); });
			}
		})
	});
</script>
