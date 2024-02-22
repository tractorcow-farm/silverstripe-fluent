<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet type='text/xsl' href='{$AbsoluteLink('styleSheet')}'?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
        xmlns:xhtml="http://www.w3.org/1999/xhtml"
>
    <% loop $Items %>
        <url>
            <loc>$AbsoluteLink</loc>
            <% if $LastEdited %>
                <lastmod>$LastEdited.Rfc3339()</lastmod><% end_if %>
            <% if $ChangeFrequency %>
                <changefreq>$ChangeFrequency</changefreq><% end_if %>
            <% if $GooglePriority %>
                <priority>$GooglePriority</priority><% end_if %>
            <% if $LinkToXDefault %>
                <xhtml:link
                        rel="alternate"
                        hreflang="x-default"
                        href="{$absoluteBaseURL.ATT}"
                />
            <% end_if %>
            <% if $Locales %><% loop $Locales %><% if $LinkingMode != 'invalid' %>
                <xhtml:link
                        rel="alternate"
                        hreflang="$HrefLang"
                        href="$AbsoluteLink"
                />
            <% end_if %><% end_loop %><% end_if %>
            <% if $ImagesForSitemap %><% loop $ImagesForSitemap %>
                <image:image>
                    <image:loc>{$AbsoluteLink}</image:loc>
                </image:image>
            <% end_loop %><% end_if %>
        </url>
    <% end_loop %>
</urlset>
