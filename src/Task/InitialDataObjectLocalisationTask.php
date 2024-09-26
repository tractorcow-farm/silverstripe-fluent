<?php

namespace TractorCow\Fluent\Task;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use TractorCow\Fluent\Extension\FluentExtension;
use TractorCow\Fluent\Extension\FluentVersionedExtension;
use TractorCow\Fluent\Model\Locale;
use TractorCow\Fluent\State\FluentState;

class InitialDataObjectLocalisationTask extends BuildTask
{
    protected static string $commandName = 'initial-dataobject-localisation-task';

    protected string $title = 'Initial DataObject localisation (excludes SiteTree)';

    protected static string $description = 'Intended for projects which already have data when Fluent module is added.';

    /**
     * When extending this class, you may choose to include only these specific classes.
     * Adding any classes here will disable `$this->exclude_classes`.
     * @var string[]
     */
    protected $include_only_classes = [];

    /**
     * When extending this class, you may choose to exclude these specific classes.
     * This is IGNORED if `$this->include_only_classes` is not empty.
     * @var string[]
     */
    protected $exclude_classes = [
        SiteTree::class
    ];

    /**
     * @throws \ReflectionException
     * @throws \SilverStripe\Core\Validation\ValidationException
     */
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $output->writeForHtml('<pre>');

        $publish = $input->getOption('publish');
        $limit = (int)$input->getOption('limit');

        $total_results = [
            'localisable' => 0,
            'localised' => 0,
            'publishable' => 0,
            'published' => 0,
        ];

        /** @var Locale $globalLocale */
        $globalLocale = Locale::get()
            ->filter(['IsGlobalDefault' => 1])
            ->sort('ID', 'ASC')
            ->first();

        if (!$globalLocale) {
            $output->writeln('<error>Please set global locale first!</>');
            $output->writeForHtml('</pre>');
            return Command::INVALID;
        }

        if ($this->include_only_classes && is_array($this->include_only_classes)) {
            $classesWithFluent = $this->include_only_classes;
            foreach ($this->include_only_classes as $key => $dataClass) {
                if (!$this->isClassNamePermitted($dataClass)) {
                    $output->writeln(sprintf('ERROR: `%s` does not have FluentExtension installed. Continuing without it...', $dataClass));
                    unset($classesWithFluent[$key]);
                }
            }
        } else {
            $dataClasses = static::getDirectSubclassesRecursivelyFor(DataObject::class);
            $classesWithFluent = $this->filterPermittedClassesRecursively($dataClasses);
        }

        foreach ($classesWithFluent as $classWithFluent) {
            if (!$this->isClassNamePermitted($classWithFluent)) {
                continue;
            }

            $results = $this->doLocaliseClass($classWithFluent, $globalLocale, $limit, $publish);
            foreach ($results as $key => $value) {
                $total_results[$key] += $value;
            }

            $output->writeln(sprintf('Processing %s objects...', $classWithFluent));
            $output->writeln(sprintf('└─ Localised %d of %d objects.', $results['localised'], $results['localisable']));
            if ($results['publishable']) {
                $output->writeln(sprintf('└─ Published %d of %d objects.', $results['published'], $results['publishable']));
            }
        }

        $output->writeln('');
        $output->writeln(sprintf('Completed %d classes.', count($classesWithFluent)));
        $output->writeln(sprintf('└─ Localised %d of %d objects in total.', $total_results['localised'], $total_results['localisable']));
        $output->writeln('');

        if ($total_results['publishable']) {
            $output->writeln(sprintf('└─ Published %d of %d objects in total.', $total_results['published'], $total_results['publishable']));
            $output->writeln('');
        }

        $output->writeForHtml('<pre>');
        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('publish', null, InputOption::VALUE_NONE, 'Publish pages after localising (if they were published beforehand)'),
            new InputOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of records to localise at once', 1),
        ];
    }

    /**
     * @param $className
     * @param $globalLocale
     * @param $limit
     * @param $publish
     * @return array{localisable: int, localised: int, publishable: int, published: int}
     * @throws \SilverStripe\Core\Validation\ValidationException
     */
    protected function doLocaliseClass($className, $globalLocale, $limit, $publish): array
    {
        $dataObjectIDs = FluentState::singleton()->withState(static function (FluentState $state) use ($className, $limit): array {
            $state->setLocale(null);
            $dataObjects = $className::get()->sort('ID', 'ASC');

            if ($limit > 0) {
                $dataObjects = $dataObjects->limit($limit);
            }

            return $dataObjects->column('ID');
        });

        return FluentState::singleton()->withState(
            static function (FluentState $state) use ($className, $globalLocale, $publish, $dataObjectIDs): array {
                $state->setLocale($globalLocale->Locale);
                $return = [
                    'localisable' => 0,
                    'localised' => 0,
                    'publishable' => 0,
                    'published' => 0,
                ];

                foreach ($dataObjectIDs as $dataObjectID) {
                    /** @var DataObject|FluentExtension $dataObject */
                    $dataObject = $className::get()->byID($dataObjectID);
                    $return['localisable'] += 1;

                    if (!$dataObject->hasExtension(FluentVersionedExtension::class)) {
                        if ($dataObject->existsInLocale()) {
                            continue;
                        }
                        $dataObject->write();
                        $return['localised'] += 1;
                        continue;
                    }

                    // We have versioned data, so start tracking how many have been published
                    $return['publishable'] += 1;

                    /** @var DataObject|Versioned|FluentVersionedExtension $dataObject */
                    if ($dataObject->isDraftedInLocale()) {
                        continue;
                    }
                    $dataObject->writeToStage(Versioned::DRAFT);

                    $return['localised'] += 1;

                    if (!$publish) {
                        continue;
                    }

                    // Check if the base record was published - if not then we don't need to publish
                    // as this would leak draft content, we only want to publish pages which were published
                    // before Fluent module was added
                    $dataObjectID = $dataObject->ID;
                    $isBaseRecordPublished = FluentState::singleton()->withState(
                        static function (FluentState $state) use ($className, $dataObjectID): bool {
                            $state->setLocale(null);
                            $page = $className::get_by_id($dataObjectID);

                            if ($page === null) {
                                return false;
                            }

                            return $page->isPublished();
                        }
                    );

                    if (!$isBaseRecordPublished) {
                        continue;
                    }

                    $dataObject->publishRecursive();
                    $return['published'] += 1;
                }

                return $return;
            }
        );
    }

    /**
     * @param string $className
     * @return array[]
     * @throws \ReflectionException
     */
    protected static function getDirectSubclassesRecursivelyFor(string $className): array
    {
        $directSubclasses = [];
        foreach (ClassInfo::subclassesFor($className, false) as $subclassName) {
            $actualParentClass = get_parent_class($subclassName);
            if ($className === $actualParentClass) {
                $directSubclasses[$subclassName] = static::getDirectSubclassesRecursivelyFor($subclassName);
            }
        }

        return $directSubclasses;
    }

    /**
     * @param array $classes
     * @return array
     */
    protected function filterPermittedClassesRecursively(array $classes): array
    {
        $permittedClasses = [];
        foreach ($classes as $parentClassName => $subclassNames) {
            if ($this->isClassNamePermitted($parentClassName)) {
                $permittedClasses[] = $parentClassName;
                // We will skip all subclasses since the ORM will automatically
                // pull them in when this parent is referenced
                continue;
            }

            $permittedClasses = array_merge($permittedClasses, $this->filterPermittedClassesRecursively($subclassNames));
        }

        return $permittedClasses;
    }

    /**
     * @param string $className
     * @return bool
     */
    protected function isClassNamePermitted(string $className): bool
    {
        // Do a simple (inexpensive) text comparison against the exclusion list before we create an object
        if (!$this->include_only_classes && is_array($this->exclude_classes) && in_array($className, $this->exclude_classes)) {
            return false;
        }

        /** @var DataObject $dataObject */
        $dataObject = singleton($className);

        // Now we'll do a full comparison against the exclusion list
        // This important step will, for example, match (refuse) a BlogPost if Page is listed as excluded
        if (is_array($this->exclude_classes)) {
            foreach ($this->exclude_classes as $excluded_class) {
                if ($dataObject instanceof $excluded_class) {
                    return false;
                }
            }
        }

        return $dataObject->hasExtension(FluentExtension::class);
    }

    public static function getHelp(): string
    {
        $isCli = Director::is_cli();
        $limit = $isCli ? '--limit=N' : 'limit=N';
        $publish = $isCli ? '--publish' : 'publish=1';
        return <<<TXT
        This dev task will localise / publish all DataObjects in the default locale. Locale setup has to be done before running this task.
        Pass <info>$limit</> to limit number of records to localise. Pass <info>$publish</> to enable publishing of localised Versioned DataObjects.
        Regardless, Versioned DataObjects which were not already published will not be published, only localised. DataObjects which were already localised will always be skipped.
        This class may be extended to create custom initialization tasks targeting or excluding specific classes.
        TXT;
    }
}
