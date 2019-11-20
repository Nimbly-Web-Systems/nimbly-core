<label>[field-name name="[item.name]"]</label>
<input type="hidden" name="[item.key][item.max]" value=0 id="[item.key]_closure">
<table class="nb-table" id="gallery_[get record.uuid default=new]">
	<thead></thead>
	<tbody class="nb-sortable"></tbody>
	<tfoot>
		<tr>
			<td colspan=4 id="[item.key]_upload">
				[progress-bar]
				<button class="nb-button" data-upload='{"trigger": "[item.key]_upload"}'>Upload</button>
				<button class="nb-button" id="[item.key]_select" data-modal='{"url": "img-select", "uid": "[item.key]_select"}'>Select</button>
				<button class="nb-button" id="[item.key]_vimeo" 
					data-modal='{"url": "get-value", "uid": "[item.key]_vimeo", "title": "Enter Vimeo ID"}'
					data-modal-done='{"trigger": "modal_vimeo_id"}'>
					Vimeo
				</button>
			</td>
		</tr>
	</tfoot>
</table>

<template id="tpl_gallery_[get record.uuid default=new]_row_img">
	<tr class="gallery-image-row">
		<td>((media_nr))</td>
		<td>
			<img src='[empty-img]' 
				data-cover-img=true
				data-empty=false 
				data-edit-img='((field_name_cover))' 
				data-img-uuid=((media_uuid_cover))
				data-img-mode='fit' style="height:100px; width: 150px; object-fit: scale-down; object-position: left;" />
		</td>
		<td>
			<img src='[empty-img]' 
				data-empty=false 
				data-edit-img='((field_name))' 
				data-img-uuid=((media_uuid))
				data-img-mode='fit' style="height:100px; width: 150px; object-fit: scale-down; object-position: left;" />
				((media_name))
		</td>
		<td>
			<a href='#' class='nb-button icon-button nb-icon-up' data-move-up></a>
			<a href='#' class='nb-button icon-button nb-icon-down' data-move-down></a>
		</td>
	</tr>
</template>

<template id="tpl_gallery_[get record.uuid default=new]_row_vid">
	<tr class="gallery-image-row gallery-video-row">
		<td>((media_nr))</td>
		<td>
			<img src='[empty-img]' 
				data-empty=false 
				data-cover-img=true
				data-edit-img='((field_name_cover))' 
				data-img-uuid=((media_uuid_cover))
				data-img-mode='fit' style="height:100px; width: 150px; object-fit: scale-down; object-position: left;" />
		</td>
		<td>
			<video width=240 
				data-empty=false 
				data-edit-img='((field_name))' 
				data-img-uuid=((media_uuid))
			>
				<source src="[base-url]/video/((media_uuid))#t=0.1" type="((media_type))">
				Video not supported by your browser.			
			</video>
			((media_name))
		</td>
		<td>
			<a href='#' class='nb-button icon-button nb-icon-up' data-move-up></a>
			<a href='#' class='nb-button icon-button nb-icon-down' data-move-down></a>
		</td>
	</tr>
</template>

<script>

$script.ready('app', function() {

if (typeof window.gallery_js_loaded === 'undefined') {
	[include [get-path]/gallery_field.js];
	$script('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js', 'jqueryui');
}

window.gallery_js_loaded = true;
$script.ready(\['jqueryui', 'edit'], function() {
	var cfg = [get-gallery-json uuid="[get record.uuid default=new]" name="[get item.key]" max="[get item.max]"];
	gallery_field.init(cfg);	
});

});	

</script>