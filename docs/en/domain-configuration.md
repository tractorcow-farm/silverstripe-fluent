# Domain Configuration

While Fluent may be tested locally using the default routing pattern, when deployed live it can be configured to
associate certain domain names with specific locales. Each domain may in this way act as a subsite, each of which
has its own default locale, sublocales, while still acting as a single multi-domain application.

## Example

For example, your website may have the following URLs locally:

 * http://localhost/mysite/ (en_US default locale)
 * http://localhost/mysite/es_US/
 * http://localhost/mysite/es_ES/
 * http://localhost/mysite/zh_cmn/
 * http://localhost/mysite/zh_yue/

When deploying this website to the live environment, you may wish to use the following as the public urls for each:

 * http://www.example.com/ (en_US default for .com)
 * http://www.example.com/es_US/
 * http://www.example.com.es/ (es_ES only for .com.es)
 * http://www.example.cn/ (zh_cmn default for .cn)
 * http://www.example.cn/zh_yue/

Although we have three domains, we can still use five locales, and allow each domain to have their own default locale.

## Configuration

Configuration of these domains is done on the "Domains" section of the `/admin/locales`
CMS admin.

Each locale has these fields in the CMS editor:
 - `Domain Hostname`: Full hostname to match this domain
 - `Locales`: List of locales assigned to this domain
 - `Default Locale`: A dropdown which allows one of the above locales to be assigned the default

Note that every locale can be associated with only one domain. Nevertheless every domains will list all locales in
their [LocaleMenu](templating.md#templating-for-fluent).

## Deployment

In order to ensure that the routing scheme will respect these domains, you should ensure the following during
deployment:

Either one of:

 * Ensure that all domains configured are the only domains that the site can be accessed under, or
 * Add `SS_FLUENT_FORCE_DOMAIN=true` to your `.env` file, or
 * Set the `TractorCow\Fluent\Extension\FluentDirectorExtension.force_domain` config to true.
   (this will affect your development environment).

Outside of these conditions, the domains configuration property will be entirely ignored, meaning you will not normally
need to alter your SilverStripe configuration between environments.
