# Installation

Fluent can be easily installed on any already-developed website

Please ensure that your site is configured before attempting to install fluent.

Read the [configuration documentation here](docs/en/configuration.md).

 * Either extract the module into the `fluent` folder, or install using composer

```bash
composer require "tractorcow/silverstripe-fluent": "3.1.*@dev"
```

 * Ensure that all dataobjects have been correctly configured for localisation
   (see [Configuration](#configuration) for details)

 * Run a dev/build to ensure all additional table fields have been generated