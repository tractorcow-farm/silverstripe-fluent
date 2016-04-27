# Installation

Fluent can be easily installed on any already-developed website

 * Please ensure that your site is configured before attempting to install Fluent.
   Read the [configuration documentation here](configuration.md).

 * Either extract the module into the `fluent` folder, or install using [composer](https://getcomposer.org)

```bash
composer require "tractorcow/silverstripe-fluent" "3.1.*-dev"
```

 * Run a `dev/build` to ensure all additional table fields have been generated
 * If migrating from the [Translatable module](https://github.com/silverstripe/silverstripe-translatable) make
   sure to check the [Translatable migration guide](translatable.md)
