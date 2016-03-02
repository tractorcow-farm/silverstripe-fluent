<?php

/**
 * Helper class to validate fluent configuration
 *
 * @todo Unit tests
 */
class FluentValidateTask extends BuildTask
{
    protected $title = "Fluent validation task";

    protected $description = "Validate and report on any Fluent config errors";

    public function run($request) {
        $result = $this->validateConfig();

        if($result->valid()) {
            Debug::message("Fluent config is valid!", false);
        } else {
            Debug::message(sprintf(
                "Fluent config invalid: %d errors found!",
                count($result->messageList())
            ), false);
            foreach($result->messageList() as $message) {
                Debug::message($message, false);
            }
        }
    }

    /**
     * Get validation result for a given config
     *
     * @param Config $config
     * @return ValidationResult
     */
    public function validateConfig(Config $config = null) {
        $result = new ValidationResult();

        // Use current config if omitted
        if(!$config) {
            $config = Config::inst();
        }

        $this->validateLocales($config, $result);
        $this->validateDomains($config, $result);

        return $result;
    }

    /**
     * Validates top level locale list
     *
     * @param Config $config
     * @param ValidationResult $result
     */
    protected function validateLocales(Config $config, ValidationResult $result) {
        $default = $config->get('Fluent', 'default_locale');
        $locales = $config->get('Fluent', 'locales');
        $i8nDefault = $config->get('i18n', 'default_locale');
        $aliases = $config->get('Fluent', 'aliases');

        if(empty($locales)) {
            $result->error("Fluent.locales config is empty!");
            return;
        }

        // Check default
        if($default && !in_array($default, $locales)) {
            $result->error(sprintf(
                'Fluent.default_locale "%s" isn\'t a valid locale in Fluent.locales',
                $default
            ));
        }

        // Check i18n
        if($i8nDefault && !in_array($i8nDefault, $locales)) {
            $result->error(sprintf(
                'i18n.default_locale "%s" isn\'t a valid locale in Fluent.locales',
                $i8nDefault
            ));
        }

        // Check aliases
        if(!empty($aliases)) {
            $seen = array();
            foreach($aliases as $alias => $aliasAs) {
                // Validate alias is a locale
                if(!in_array($alias, $locales)) {
                    $result->error(sprintf(
                        'Fluent.aliases locale "%s" isn\'t a valid locale in Fluent.locales',
                        $alias
                    ));
                }

                // Check duplicates
                if(isset($seen[$aliasAs])) {
                    $result->error(sprintf(
                        'Fluent.aliases has duplicate locales aliased as "%s"',
                        $aliasAs
                    ));
                }
                $seen[$aliasAs] = $aliasAs;
            }
        }
    }

    /**
     * Validates domains config
     *
     * @param Config $config
     * @param ValidationResult $result
     */
    protected function validateDomains(Config $config, ValidationResult $result)
    {
        $domains = $config->get('Fluent', 'domains');
        $locales = $config->get('Fluent', 'locales');
        if(empty($domains)) {
            // Skip sites without domain config
            return;
        }

        // Validate each locale
        $seen = array();
        foreach($domains as $domain => $domainConfig) {
            if(empty($domainConfig['locales'])) {
                $result->error(sprintf('Domain "%s" has no locales configured', $domain));
                continue;
            }

            // Check default locale
            if(!empty($domainConfig['default_locale'])) {
                $domainDefaultLocale = $domainConfig['default_locale'];

                // Check locale is valid in global list
                if(!in_array($domainDefaultLocale, $locales)) {
                    $result->error(sprintf(
                        'Domain "%s" default_locale "%s" isn\'t a valid locale in Fluent.locales',
                        $domain,
                        $domainDefaultLocale
                    ));
                }

                // Check default_locale matches sub locales
                if(!in_array($domainDefaultLocale, $domainConfig['locales'])) {
                    $result->error(sprintf(
                        'Domain "%s" default_locale "%s" isn\'t a valid locale in Fluent.domains.%s.locales',
                        $domain,
                        $domainDefaultLocale,
                        $domain
                    ));
                }
            }

            // Check each locale
            foreach($domainConfig['locales'] as $domainLocale) {
                // Check duplicates
                if(isset($seen[$domainLocale])) {
                    $result->error(sprintf(
                        'Domain "%s" locale "%s" is already assigned to another domain',
                        $domain,
                        $domainLocale
                    ));
                }
                $seen[$domainLocale] = $domainLocale;

                // Check domain is valid
                if(!in_array($domainLocale, $locales)) {
                    $result->error(sprintf(
                        'Domain "%s" has locale "%s" which isn\'t a valid locale in Fluent.locales',
                        $domain,
                        $domainLocale
                    ));
                }
            }

        }

        // Check that all locales are seen
        $unseenLocales = array_diff($locales, $seen);
        if($unseenLocales) {
            foreach($unseenLocales as $unseenLocale) {
                $result->error(sprintf(
                    'Fluent.locales locale "%s" does not appear in any domain.',
                    $unseenLocale
                ));
            }
        }
    }
}
