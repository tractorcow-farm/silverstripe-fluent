<% if $Locales %><% loop $Locales %><% if $IsPublished && $canViewInLocale %>
    <link rel="alternate" hreflang="$HrefLang.ATT" href="$AbsoluteLink.ATT" />
<% end_if %><% end_loop %><% end_if %>
