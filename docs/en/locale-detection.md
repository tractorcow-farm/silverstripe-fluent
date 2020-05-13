# Locale detection

When a visitor lands on the home page for the first time,
Fluent can also attempt to detect that user's locale based
on the `Accept-Language` http headers sent.

This functionality can interfere with certain applications, such as Facebook Open Graph tools, so it
is turned off by default. To turn it on set the below setting:

```yaml
TractorCow\Fluent\Extension\FluentDirectorExtension:
  detect_locale: true
```

## Configuring detection mechanism

The default detection mechanism is based on the Accept-Language header. However you can
inject a substitute detection logic below:

```yaml
---
Name: myapp
After:
  - "#fluentdetection"
---
SilverStripe\Core\Injector\Injector:
  TractorCow\Fluent\State\LocaleDetector:
    class: App\Fluent\MyLocaleDetector
```

Then make sure that your `App\Fluent\MyLocaleDetector` class implements the
`TractorCow\Fluent\State\LocaleDetector` interface.

## Cloudflare

If your site uses cloudflare you can hook into its IP Detection mechanism.

Follow the [Cloudflare documentation](https://support.cloudflare.com/hc/en-us/articles/200168236-Configuring-Cloudflare-IP-Geolocation)
to enable this feature before proceeding.

Then you can turn on the detector in code using the below:

```yaml
---
Name: myapp
After:
- "#fluentdetection"
---
TractorCow\Fluent\Extension\FluentDirectorExtension:
  detect_locale: true
SilverStripe\Core\Injector\Injector:
  TractorCow\Fluent\State\LocaleDetector:
    class: TractorCow\Fluent\State\CloudflareLocaleDetector
```
