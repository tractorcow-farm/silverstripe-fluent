---
title: Installation
summary: Installing the Fluent module and initial setup
icon: download
---

# Installation

Fluent can be easily installed on any already-developed website, but must be installed

```bash
composer require tractorcow/silverstripe-fluent
```

After installing, here are some common steps you should do:

- Run a `dev/build` with `flush=1` to ensure all additional table fields have been generated
- Configure your locales in the `/admin/locales` section
- Publish pages in each of the locales you want them to be visible in

Fluent will automatically localise SiteTree objects. If you want to localise other DataObjects you will need to
add the appropriate extension yourself.

Please note that if your DataObject is versioned you will need to use the
[`FluentVersionedExtension`](api:TractorCow\Fluent\Extension\FluentVersionedExtension), and it must be applied *after* the [`Versioned`](api:SilverStripe\Versioned\Versioned) extension. You can achieve this by
using an `after: '#versionedfiles'` condition in your YAML configuration block title.

For more information please see [Configuration](./03_configuration.md).
