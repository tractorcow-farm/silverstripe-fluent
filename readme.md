# Fluent

## Simple Silverstripe Localisation

This module allows websites to manage localisation of content, and navigation between localisations,
in a similar fashion to [Translatable](https://github.com/silverstripe/silverstripe-translatable)
or [Multilingual](https://github.com/kreationsbyran/multilingual).

Locales are distinguished by a url prefix, that of the selected locale, at the start
of all page links. E.g. `http://damian.geek.nz/en_NZ/about-me` would be the NZ English
version of a page. This could be localised into Maori at `http://damian.geek.nz/mi_NZ/about-me`

Back end control is provided by a simple CMS filter.

_Please read the [Configuration](#configuration) section before trying to install!_

Also, please [report any issues](https://github.com/tractorcow/silverstripe-fluent/issues)
you may encounter, as it helps us all out!

## Credits and Authors

 * Damian Mooyman - <https://github.com/tractorcow/silverstripe-fluent>
 * Attribution to Michael (dAKirby309) for his metro translate icon - <http://dakirby309.deviantart.com/>

## Requirements

 * SilverStripe 3.1
 * PHP 5.3

## Configuration

Installation runs more smoothly if you configure your site for localisation before
installing the module, as it will rebuild the database based on configuration.
Good to read this bit first!

Please check [fluent.yml](_config/fluent.yml) for the default configuration settings.

### Locale configuration

Firstly, you'll need to configure the locales that should be included, as well as
the default locale.

By default the list is blank. You should add the following to your mysite/_config/fluent.yaml

It's advisable to set the default i18n locale to match your site locale

Below demonstrates a typical north american website.

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
	- en_CA
    - fr_CA
---
Name: myfluenti18nconfig
After: '#fluenti18nconfig'
---
i18n:
  default_locale: en_US
```

### Field localisation configuration

Great, now we've setup our languages. Our next job is to decide which dataobjects, and which
fields of those dataobjects, should be localised.

The best way to do this is to set the 'translate' config option on the dataobject,
set to the fields you want localised. Note that this must be on the same class
as the database field is specified.

```yaml
---
Name: myblogconfig
---
BlogEntry:
  translate:
    - 'Tags'
BlogTree:
  translate:
    - 'Name'
```

If you want to localise a `has_one` relation then you can add the field (with 'ID'
suffix included).

```yaml
BlogHolder:
  translate:
    - 'OwnerID'
```

Note: If you wish to translate `has_many` or `many_many` then those objects will need
to be filtered via another method. See [Locale based filter configuration](#locale-based-filter-configuration)

If you want to localise a dataobject that doesn't extend sitetree then you'll need
to add the appropriate extension

```yaml
---
Name: myextensions
---
MyDataObject:
  extensions:
    - 'FluentExtension'
```

If you are using custom controllers (such as for rendering rss, ajax data, etc) you
should probably also add the `FluentContentController` extension in order to ensure
the locale is set correctly for generated content.

```yaml
---
Name: mycontrollerconfig
---
MyAjaxController:
  extensions:
    - 'FluentContentController'
```

### Locale based filter configuration

In addition to localising fields within a DataObject, a filter can also be applied
to conditionally show or hide DataObjects within specific locales.

For instance, if there's an object that could potentially be visible only on
certain locations (such as a product with limited availability in other countries)
then you can add the `FluentFilteredExtension` in order to add additional filter
options in the CMS.

Note: It's not necessary to actually localise this object in order for it to be
filterable; `FluentFilteredExtension` and `FluentExtension` each work independently.

```yaml
---
Name: myproductconfiguration
---
ProductObject:
  extensions:
    - 'FluentFilteredExtension'
```

Make sure that if you are filtering a non-sitetree object that you use the following
in your getCMSFields in order to add the necessary filter:

```php
function getCMSFields() {
	$fields = new FieldList(
		new TextField('Title', 'Title', null, 255)
	);
	$this->extend('updateCMSFields', $fields);
	return $fields;
}
```

Now when editing this item in the CMS there will be an additional set of checkboxes
labelled "Locale filter".

Note: Although these objects will be filtered in the front end, this filter is disabled
in the CMS in order to allow access by site administrators in all locales.

## Installation Instructions

Fluent can be easily installed on any already-developed website

 * Either extract the module into the `fluent` folder, or install using composer

```bash
composer require "tractorcow/silverstripe-fluent": "3.1.*@dev"
```

 * Ensure that all dataobjects have been correctly configured for localisation
   (see [Configuration](#configuration) for details)

 * Run a dev/build to ensure all additional table fields have been generated

## Website template

On the front end of the website you can include the `LocaleMenu.ss` template to provide
a simple locale navigation.

```html
<% include LocaleMenu %>
```

## How it works

As opposed to Translatable which manages separate `SiteTree` objects for multiple 
localisations, Fluent stores all localisations for properties on the same table row.

This method has the following benefits:

 * Seamless integration with other modules and extensions (such as Versioned)
 * Allows for installation easily on existing websites
 * Minimises the amount of special case code to handle localisations; A page has the 
   same ID no matter the current locale!
 * The simplicity of the single-table method means that any object can be easily
   and transparently localised, even non-SiteTree dataobjects.
 * There is only ever one sitemap, so the page hierarchy doesn't need to be 
   duplicated for each additional locale.

Fluent has a couple of built in rules for determining which fields to localise, but
these can be easily customised on a per-object bases (or even by customising the 
global ruleset).

When querying data the SQL is augmented to replace all SELECT fragments for those
fields with conditionals; It will detect if a value for the localised field (such
as `Title_en_NZ`) exists, and use this if it does, otherwise using the base field
(`Title`) as the default. When a dataobject is written, the inverse is performed,
ensuring that the field related to the current locale is correctly written to.

Unfortunately, there's currently no localisation mechanism for sitetree urls
(for the sake of simplicity). This could be implemented if requested however. :)

## License

Copyright (c) 2013, Damian Mooyman
All rights reserved.

All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

 * Redistributions of source code must retain the above copyright
   notice, this list of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright
   notice, this list of conditions and the following disclaimer in the
   documentation and/or other materials provided with the distribution.
 * The name of Damian Mooyman may not be used to endorse or promote products
   derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
