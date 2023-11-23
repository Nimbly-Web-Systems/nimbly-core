<button class="[btn-class-secondary] ml-2" type="button" data-te-ripple-init data-te-ripple-color="light"
	id="nb_btn_delete_all"
	data-te-toggle="popconfirm" data-te-popconfirm-mode="inline"
	data-te-ok-text="[text Delete all]"
	data-te-class-popover="w-[300px] border-[1px] border-solid border-cnormal bg-clight rounded-[0.5rem] z-[1080] shadow-lg"
	data-te-message="[text Delete all `[field-name [data.resource]]` records? Are you sure?]">
	[text Delete all records]
</button>
<script>
	const nb_btn_delete_all = document.getElementById('nb_btn_delete_all');
	nb_btn_delete_all.addEventListener('confirm.te.popconfirm', (event) => {
		if (event.target != nb_btn_delete_all) {
			return;
		}
		nb.api.get("[base_url]/api/v1/empty-resource?resource=[data.resource]").then((data) => {
			console.log('here');
			if (data.success) {
				nb.api.post('[base_url]/api/v1/.system-messages', { message: "[text Deleted all records]"}).then((data) =>
				{ window.location.reload(); });
			}
		})
	});
</script>