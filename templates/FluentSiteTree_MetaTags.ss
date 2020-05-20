<% if $LinkToXDefault %>
    <link rel="alternate" hreflang="x-default" href="{$absoluteBaseURL.ATT}" />
<% end_if %>
<% if $Locales %><% loop $Locales %><% if $IsPublished && $canViewInLocale %>
    <link rel="alternate" hreflang="$HrefLang.ATT" href="$AbsoluteLink.ATT" />
<% end_if %><% end_loop %><% end_if %>
