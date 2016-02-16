# Domain Configuration

While fluent may be tested locally using the default routing pattern, when deployed live it can be configured to
associate certain domain names with specific locales. Each domain may in this way act as a subsite, each of which
has its own default locale, sublocales, while still acting as a single multi-domain application.

## Example

For example, your website may have the following urls locally:

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

The fluent.yml configuration for the above example would look like the above:

```yaml
---
Name: myfluentconfig
After: '#fluentconfig'
---
Fluent:
  default_locale: en_US
  locales:
    - en_US
    - es_US
    - es_ES
    - zh_cmn
    - zh_yue
  domains:
    www.example.com:
      locales:
        - en_US
        - es_US
      default_locale: en_US
    www.example.com.es:
      locales:
        - es_ES
    www.example.cn:
      locales:
        - zh_cmn
        - zh_yue
      default_locale: zh_cmn
```

Note that every locale must be ascociated with only one domain. Nevertheless every domains will list all locales in their [LocaleMenu](templating.md#templating-for-fluent). To ensure a valid configuration you can use test your configuration as described [here](configuration.md#testing-configuration).

## Deployment

In order to ensure that the routing scheme will respect these domains, you should ensure the following during deployment:

Either one of:

 * Ensure that all domains configured are the only domains that the site can be accessed under, or
 * Add `define('SS_FLUENT_FORCE_DOMAIN', true)` to your `_ss_environment.php` file, or
 * Add `Config::inst()->update('Fluent', 'force_domain', true)` to your config (this will effect your development environment)

Outside of these conditions, the domains configuration property will be entirely ignored, meaning you will not normally need to alter
your silverstripe configuration between environments.
