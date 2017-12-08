# Templating for Fluent

On the front end of the website you can include the `LocaleMenu.ss` template to provide
a simple locale navigation.

```html
<% include LocaleMenu %>
```

If you are using partial caching then you will need to ensure the current locale is a part of the cache key.

```html
<% cached 'navigation', List(Page).max(LastEdited), $CurrentLocale %>
	<% loop Menu(1) %>	  
		<li class="$LinkingMode"><a href="$Link" title="$Title.XML">$MenuTitle.XML</a></li>
	<% end_loop %>
<% end_cached %>
```