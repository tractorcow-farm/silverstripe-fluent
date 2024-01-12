<?php

namespace TractorCow\Fluent\Task;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLSelect;
use SilverStripe\Security\DefaultAdminService;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentFilteredExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Task\ConvertTranslatableTask\Exception;

/**
 * Provides migration from the Translatable module in a SilverStripe 3 website to the Fluent format for SilverStripe 4.
 * This task assumes that you have upgraded your website to run on SilverStripe 4 already, and you want to migrate the
 * existing data from your project into a format that is compatible with Fluent.
 *
 * Don't forget to:
 *
 * 1. Back up your DB
 * 2. dev/build
 * 3. Log into the CMS and set up the locales you want to use
 * 4. Back up your DB again
 * 5. Log into the CMS and check everything
 */
class ConvertTranslatableTask extends BuildTask
{
    protected $title = "Convert Translatable > Fluent Task";

    protected $description = "Migrates site DB from SS3 Translatable DB format to SS4 Fluent.";

    private static $segment = 'ConvertTranslatableTask';

    /**
     * Checks that fluent is configured correctly
     *
     * @throws ConvertTranslatableTask\Exception
     */
    protected function checkInstalled()
    {
        // Assert that fluent is configured
        $locales = Locale::getLocales();
        if (empty($locales)) {
            throw new Exception("Please configure Fluent locales (in the CMS) prior to migrating from translatable");
        }

        $defaultLocale = Locale::getDefault();
        if (empty($defaultLocale)) {
            throw new Exception(
                "Please configure a Fluent default locale (in the CMS) prior to migrating from translatable"
            );
        }
    }

    /**
     * Gets all classes with FluentExtension
     *
     * @return array Array of classes to migrate
     */
    public function fluentClasses()
    {
        $classes = [];
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        array_shift($dataClasses);
        foreach ($dataClasses as $class) {
            $base = DataObject::getSchema()->baseDataClass($class);
            foreach (DataObject::get_extensions($base) as $extension) {
                if (is_a($extension, FluentExtension::class, true)) {
                    $classes[] = $base;
                    break;
                }
            }
        }
        return array_unique($classes);
    }

    public function run($request)
    {
        $this->checkInstalled();

        // we may need some privileges for this to work
        // without this, running under sake is a problem
        // maybe sake could take care of it ...
        Member::actAs(
            DefaultAdminService::singleton()->findOrCreateDefaultAdmin(),
            function () {
                DB::get_conn()->withTransaction(function () {
                    Versioned::set_stage(Versioned::DRAFT);
                    $classes = $this->fluentClasses();
                    $tables = DB::get_schema()->tableList();
                    if (empty($classes)) {
                        Debug::message('No classes have Fluent enabled, so skipping.', false);
                    }

                    foreach ($classes as $class) {
                        /** @var DataObject $class */

                        // Ensure that a translationgroup table exists for this class
                        $baseTable = DataObject::getSchema()->baseDataTable($class);
                        $groupTable = strtolower($baseTable . "_translationgroups");
                        if (isset($tables[$groupTable])) {
                            $groupTable = $tables[$groupTable];
                        } else {
                            Debug::message("Ignoring class without _translationgroups table $class", false);
                            continue;
                        }

                        // Disable filter if it has been applied to the class
                        if (singleton($class)->hasMethod('has_extension')
                            && $class::has_extension(FluentFilteredExtension::class)
                        ) {
                            $class::remove_extension(FluentFilteredExtension::class);
                        }

                        // Select all instances of this class in the base table, where the Locale field is not null.
                        // Translatable has a Locale column on the base table in SS3, but Fluent doesn't use it. Newly
                        // created records via SS4 Fluent will not set this column, but will set it in {$baseTable}_Localised
                        $instances = DataObject::get($class, sprintf(
                            '"%s"."Locale" IS NOT NULL',
                            $baseTable
                        ));

                        foreach ($instances as $instance) {
                            // Get the Locale column directly from the base table, since the SS ORM will not include it
                            $instanceLocale = SQLSelect::create()
                                ->setFrom("\"{$baseTable}\"")
                                ->setSelect('"Locale"')
                                ->setWhere(["\"{$baseTable}\".\"ID\"" => $instance->ID])
                                ->execute()
                                ->first();

                            // Ensure that we got the Locale out of the base table before continuing
                            if (empty($instanceLocale['Locale'])) {
                                Debug::message("Skipping {$instance->Title} with ID {$instance->ID} - couldn't find Locale");
                                continue;
                            }
                            $instanceLocale = $instanceLocale['Locale'];

                            // Check for obsolete classes that don't need to be handled any more
                            if ($instance->ObsoleteClassName) {
                                Debug::message(
                                    "Skipping {$instance->ClassName} with ID {$instance->ID} because it from an obsolete class",
                                    false
                                );
                                continue;
                            }

                            Debug::message(
                                "Updating {$instance->ClassName} {$instance->Title} ({$instance->ID}) with locale {$instanceLocale}",
                                false
                            );

                            FluentState::singleton()
                                ->withState(function (FluentState $state) use ($instance, $instanceLocale) {
                                    // Use Fluent's ORM to write and/or publish the record into the correct locale
                                    // from Translatable
                                    $state->setLocale($instanceLocale);

                                    if (!$this->isPublished($instance)) {
                                        $instance->write();
                                        Debug::message("  --  Saved to draft", false);
                                    } elseif ($instance->publishRecursive() === false) {
                                        Debug::message("  --  Publishing FAILED", false);
                                        throw new Exception("Failed to publish");
                                    } else {
                                        Debug::message("  --  Published", false);
                                    }
                                });
                        }

                        // Drop the "Locale" column from the base table
                        Debug::message('Dropping "Locale" column from ' . $baseTable, false);
                        DB::query(sprintf('ALTER TABLE "%s" DROP COLUMN "Locale"', $baseTable));

                        // Drop the "_translationgroups" translatable table
                        Debug::message('Deleting Translatable table ' . $groupTable, false);
                        DB::query(sprintf('DROP TABLE IF EXISTS "%s"', $groupTable));
                    }
                });
            }
        );
    }

    /**
     * Determine whether the record has been published previously/is currently published
     *
     * @param DataObject $instance
     * @return bool
     */
    protected function isPublished(DataObject $instance)
    {
        $isPublished = false;
        if ($instance->hasMethod('isPublished')) {
            $isPublished = $instance->isPublished();
        }
        return $isPublished;
    }
}
