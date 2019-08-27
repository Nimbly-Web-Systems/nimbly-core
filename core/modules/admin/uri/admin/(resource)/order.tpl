<script>

$script.ready('app', function() {

if (typeof window._order_js_loaded === 'undefined') {
	[include [get-path]/order.js];
	$script('https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js', 'jqueryui');
}

window._order_js_loaded = true;
$script.ready(\['jqueryui'], function() {
	order.init();	
});

});	

</script>