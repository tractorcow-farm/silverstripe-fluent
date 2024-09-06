# Installation

 * Fluent can be easily installed on any already-developed website, but must be installed
with composer.

```bash
composer require tractorcow/silverstripe-fluent ^5
```

 * Run `sake db:build --flush` to ensure all additional table fields have been generated
 * Configure your locales in the `/admin/locales` section
 * Publish pages in each of the locales you want them to be visible in

Fluent will automatically localise SiteTree objects. If you want to localise other DataObjects you will need to
add the appropriate extension yourself.

Please note that if your DataObject is versioned you will need to use the
`FluentVersionedExtension`, and it must be applied _after_ the `Versioned` extension. You can achieve this by
using an `after: '#versionedfiles'` condition in your YAML configuration block title.

For more information please see [configuration](configuration.md).
