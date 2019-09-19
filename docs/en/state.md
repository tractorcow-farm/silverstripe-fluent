# Fluent State

When fluent is in operation, it will have a state that is first bootstrapped
by the module middleware, and then used throughout the execution of the request.

The current state can be retrieved from injector:

```php
use TractorCow\Fluent\State\FluentState;
$state = FluentState::singleton();
```

## Members

This state has the following members:

 - Locale - The current locale of this request
 - Domain - The current domain name of this request
 - IsDomainMode - Determine if the current domain filters the list of locales.
   If this is set to true, then Domain will be used to pick the default locale, as well
   as the list of available locales.
 - IsFrontend - Determine if the current request is a frontend request.
   If this is set to true, then records that are filtered in the current locale will
   filter out of subsequent requests, and localisation will behave differently than
   in the CMS.
   If this is set to false, then filtered records are not filtered out, and 
   localisation of records will always fall back to the base record.
   
   
## Mutating state

During the execution of a request, you may need to modify the state, either temporarily,
or permanently. E.g. in order to request content in another locale.

To set the state permanently, you can simply call any of the member setters on the object:

E.g.

```php
FluentState::singleton()->setLocale('en_NZ');
```

If you only need to temporarily modify the state, but wish to not modify the global state,
you can use the `withState` helper to safely perform this.


```php
// Get a list of pages with their locale set to en_NZ
$nzRecords = FluentState::singleton()->withState(function(FluentState $state) {
    $state->setLocale('en_NZ');
    return SiteTree::get(); // Localised content queried in en_NZ locale
});
```

The $state variable will be a clone of the global state,
which is then discarded after withState() is returned.

Records which are queried in any state will remember their original state, even
once the global state returns, so you can still access those records outside
of the `withState` helper.
