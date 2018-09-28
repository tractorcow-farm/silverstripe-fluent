<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\SapphireTest;
use TractorCow\Fluent\Extension\FluentReadVersionsExtension;
use TractorCow\Fluent\State\FluentState;

class FluentReadVersionsExtensionTest extends SapphireTest
{
    public function testUpdateListSetsCurrentLocaleIntoHavingInQuery()
    {
        FluentState::singleton()->withState(function (FluentState $newState) {
            $newState->setLocale('en_NZ');
            $list = SiteTree::get();

            $extension = new FluentReadVersionsExtension();
            $extension->updateList($list);

            $this->assertContains(
                ['"SourceLocale" = ?' => ['en_NZ']],
                $list->dataQuery()->getFinalisedQuery()->getHaving(),
                'Fluent adds the current locale to the underlying list\'s data query'
            );
        });
    }
}
