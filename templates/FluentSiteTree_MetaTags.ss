<% if $Locales %><% loop $Locales %><% if $LinkingMode != 'invalid' %>
	<link rel="alternate" hreflang="$LocaleRFC1766" href="$AbsoluteLink" />
<% end_if %><% end_loop %><% end_if %>
