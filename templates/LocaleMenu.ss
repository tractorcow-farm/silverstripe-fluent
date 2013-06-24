<% if Locales %>
<div class="left">Locale <span class="arrow">&rarr;</span>
	<nav class="primary">
		<ul>
			<% loop Locales %>
				<li><a href="$Link">$Title</a></li>
			<% end_loop %>
		</ul>
	</nav>
</div>
<% end_if %>