---
title: Migrating from a single language site
summary: Explains how to add Fluent to an existing single-language site and populate the initial localised content for existing pages and data objects.
icon: dolly
---

# Migrating from single language site

In case you want to add fluent to an existing site to add multi language functionality you need to:

## Install fluent

Use composer to install fluent, see [installation](./01_installation.md)

## Configure fluent

### Adding locales

You can either do this in the backend, or for the first setup you can utitlise `default_records` to add the locales to
the db.
A `fluent.yml` might look like:

```yml
---
Name: myfluentconfig
After: '#fluentconfig'
---

TractorCow\Fluent\Model\Locale:
  default_records:
    nl:
      Title: German
      Locale: de_DE
      URLSegment: de
      IsGlobalDefault: 1
    en:
      Title: English
      Locale: en_GB
      URLSegment: en
```

When you run `sake db:build --flush` again, this adds the records to the database if the locales table is still empty.

## Populating initial localised content for existing pages and `DataObject` models in your default locale

Now your site is broken because nothing has been published and added as translated data in your default locale. You can
either manually localise all DataObjects &amp; Pages manually or use one of the automation options below.

### Automated tools for localisation

#### From the CMS (`SiteTree` only)

Use Silverstripe's
built-in [publishall](https://docs.silverstripe.org/en/developer_guides/debugging/url_variable_tools/#building-and-publishing-urls)
tool to publish all Pages in bulk.
Run `/admin/pages/publishall` in your browser and your site will be fixed again and you can start adding translated
content.

*This method will work with Pages only (not localised DataObjects).*

#### Commandline or queued jobs (`SiteTree` and `DataObjects`)

The [`InitialPageLocalisationTask`](api:TractorCow\Fluent\Task\InitialPageLocalisationTask) and [`InitialDataObjectLocalisationTask`](api:TractorCow\Fluent\Task\InitialDataObjectLocalisationTask) dev tasks may be used to localise and, optionally,
publish your [versioned data](https://docs.silverstripe.org/en/developer_guides/model/versioning/#versioning) (including Pages) from the commandline or queued as a job (if the [Queued Jobs module](https://docs.silverstripe.org/en/optional_features/queuedjobs) is installed).

`InitialPageLocalisationTask` - localise all `SiteTree` objects (Pages)

`InitialDataObjectLocalisationTask` - localise all Fluent-enabled DataObjects (excluding `SiteTree`)

1. Example: Localise all pages (default, without publishing)

   ```bash
   sake tasks:initial-page-localisation-task
   ```

1. Example: Localise &amp; publish all pages

    ```bash
    sake tasks:initial-page-localisation-task --publish
    ```

1. Example: Localising pages in batches can be done by using the `limit` option.
   This will localise &amp; publish five pages on each run.

    ```bash
    sake tasks:initial-page-localisation-task --publish --limit=5
    ```

1. Example: All the same functionality is available for localising all `DataObject` models, including versioned and non-versioned classes

    ```bash
    sake tasks:initial-dataobject-localisation-task
    ```

    or

    ```bash
    sake tasks:initial-dataobject-localisation-task --publish --limit=5
    ```

#### Customize your own initialisation dev task

Perhaps you want to be more selective in how you initialise your localised content.
The `InitialDataObjectLocalisationTask` class can be easily extended to either list exactly which classes you want to
initially localise, or you can exclude specific classes from initialisation.

1. **Initialise specific classes:** The following example will create a task which localises ***ONLY*** `BlogPost`
pages, `Testimonial` objects, *and their subclasses (if any)*.

    ```php
    namespace App\Tasks;

    use AcmeCo\Model\Testimonial;
    use SilverStripe\Blog\Model\BlogPost;

    class CustomLocalisationTask extends InitialDataObjectLocalisationTask
    {
        /**
         * @var string
         */
        private static $segment = 'custom-localisation-initialisation-task';
    
        /**
         * @var string
         */
        protected $title = 'Custom localisation initialisation';
    
        /**
         * @var string[]
         */
        protected array $include_only_classes = [
            BlogPost::class,
            Testimonial::class,
        ];
    }
    ```

1. **Initialise all DataObjects but exclude some:** The following example will create a task which localises ***ALL***
DataObjects ***except*** `BlogPost` pages, `Testimonial` objects, *and their subclasses (if any)*.

    ```php
    namespace App\Tasks;

    use AcmeCo\Model\Testimonial;
    use SilverStripe\Blog\Model\BlogPost;

    class CustomLocalisationTask extends InitialDataObjectLocalisationTask
    {
        /**
         * @var string
         */
        private static $segment = 'custom-localisation-initialisation-task';
    
        /**
         * @var string
         */
        protected $title = 'Custom localisation initialisation';
    
        /**
         * @var string[]
         */
        protected array $exclude_classes = [
            BlogPost::class,
            Testimonial::class,
        ];
    }
    ```

1. **One or the other:** You may specify `$include_only_classes` OR `$exclude_classes` - not both.
If `$include_only_classes` is not an empty array, `$exclude_classes` will be ignored.
