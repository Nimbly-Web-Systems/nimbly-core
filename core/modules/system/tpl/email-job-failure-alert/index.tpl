<h2>[#text Nimbly job failed#]</h2>
<p>[#text A queued job reached its final failed status.#]</p>
<table cellpadding="4" cellspacing="0" style="border-collapse:collapse;">
    <tr><td><strong>[#text Type#]</strong></td><td>[#get failed_type#]</td></tr>
    <tr><td><strong>[#text Job UUID#]</strong></td><td>[#get failed_uuid#]</td></tr>
    <tr><td><strong>[#text Attempts#]</strong></td><td>[#get failed_attempts#]</td></tr>
    <tr><td><strong>[#text Error#]</strong></td><td>[#get failed_error#]</td></tr>
</table>
<h3>[#text Payload#]</h3>
<pre>[#get failed_payload#]</pre>
