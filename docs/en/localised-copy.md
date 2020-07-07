# Localised copy

This section assumes that the reader is already familiar with the basics on how this module localises content.

This module supports multiple ways of how to localise your data.
Localisation type of your choice should be based on your specific situation as it has a considerable impact on content management.

## Localisation types

In general, there are two ways how you can localise your data.

### Example setup

Suppose we have a simple data structure which only contains a `HomePage` and a `Banner`.

```php
class HomePage extends Page
{
    private static $db = [
        'Title' => 'Varchar',
    ];

    private static $has_one = [
        'Banner' => Banner::class,
    ];
```

```php
class Banner extends DataObject
{
    private static $db = [
        'Title' => 'Varchar',
    ];


    private static $belongs_to = [
        'Parent' => HomePage::class . '.Banner',
    ];
```

### Localise by content (AKA direct localisation)

We will apply `FluentExtension` to both classes. Note that this example is almost same in case your classes are versioned.
You need to apply `FluentVersionedExtension` instead of `FluentExtension` in such case.

The result of this setup is that the `Title` of both classes become localised.

#### Desired data state

Locale A

`HomePage` (ID 1) - `Banner` (ID 1)

Locale B

`HomePage` (ID 1) - `Banner` (ID 1)

#### Advantages

* great choice for situations where content in different locales has mostly the same structure
* simpler backend setup
* unit tests can be more difficult to write as locale is a consideration for all classes

#### Disadvantages

* poor choice for situations where content in different locales has completely different structure

##### Examples of different structure in different locales

* different number / position of links in the menu
* different number / position of blocks within the page

This issue can be somewhat remedied by using the `FluentFilteredExtension` but if the structure gets too different, the content editing quickly becomes a real challenge.

### Localise by relation (AKA indirect localisation)

We will apply `FluentExtension` to only `HomePage` and localise the `BannerID` field. Note that this example is almost the same in case your classes are versioned.
You need to apply `FluentVersionedExtension` instead of `FluentExtension` in such case.

#### Desired data state

Locale A

`HomePage` (ID 1) - `Banner` (ID 1)

Locale B

`HomePage` (ID 1) - `Banner` (ID 2)

#### Advantages

* great choice for situations where content in different locales can have quite different structure
* unit tests can be easier to write as locale may not be a consideration for some classes

#### Disadvantages

* more complex backend setup
* may require more complex CMS UI

Note that simply localising `BannerID` doesn't actually cover creation of a new `Banner` object when creating content in a new locale.
This is where the `Localised copy` feature comes in.

## How to use Localised copy feature

Following our example above, we need to make sure that when `HomePage` is localised into Locale B a new `Banner` object is created and assigned to the `HomePage` in Locale B.

### Include the feature to relevant class

Include `LocalisedCopyTrait` on your class like this:

```php
use LocalisedCopyTrait;
```

In our example, this trait will be put on `HomePage`.

### Implement copy method

The Trait forces us to implement `executeLocalisedCopy()` method. This method will be called just before our page is written into the new locale.
In this specific example we decided to create a copy of the original banner and assign it to our localised page.

```php
protected function executeLocalisedCopy(): void
{
    $original = $this->Banner();

    if (!$original->exists()) {
        return;
    }

    $duplicate = $original->duplicate();
    $this->BannerID = $duplicate->ID;
}
```

It's up to you how you want to implement this method, you can also create a completely new banner object instead of a copy.
With this method implemented our banner gets localised when we localise `HomePage`, achieving the desired data state mentioned above.

## Other use cases

Localised copy feature can be used for other purposes. Most notable cases would be:

* dispatch an event containing information about the localised page, useful for user activity logs and similar
* this feature provides an API which can be called at arbitrary times so you can copy and override content from one locale to another outside of standard flow (via CMS UI)
