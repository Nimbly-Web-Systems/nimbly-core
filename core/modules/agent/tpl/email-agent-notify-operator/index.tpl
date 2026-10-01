<h2>[#get notification_heading#]</h2>
<p>Agent: [#get agent_name#] ([#get agent_id#])</p>
[#if conversation_id=(not-empty) tpl=email-agent-notify-operator-conversation#]
<p>[#get agent_message#]</p>
[#if asked_by=(not-empty) tpl=email-agent-notify-operator-asker#]
<p style="color:#666;">[#get site_name#] · [#get environment#]</p>
