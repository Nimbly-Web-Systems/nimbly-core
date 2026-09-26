<h2>[#get agent_name#] [#text needs you#]</h2>
<p>[#get agent_message#]</p>
[#if asked_by=(not-empty) tpl=email-agent-notify-operator-asker#]
<p style="color:#666;">[#get site_name#] · [#get environment#]</p>
