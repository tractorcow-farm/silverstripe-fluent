<% if $Locales %><% loop $Locales %><% if $LinkingMode != 'invalid' %>
	<link rel="alternate" hreflang="$LocaleRFC1766.ATT" href="$AbsoluteLink.ATT" />
<% end_if %><% end_loop %><% end_if %>
