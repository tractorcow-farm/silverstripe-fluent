<?php

namespace TractorCow\Fluent\Tests\Extension;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Versioned\Versioned;
use TractorCow\Fluent\State\FluentState;
use TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest\ChildRecord;
use TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest\ParentRecord;
use TractorCow\Fluent\Tests\Extension\FluentGridFieldExtensionTest\TestAdmin;

class FluentGridFieldExtensionTest extends FunctionalTest
{
    protected static $fixture_file = 'FluentGridFieldExtensionTest.yml';

    protected static $extra_dataobjects = [
        ParentRecord::class,
        ChildRecord::class,
    ];

    /**
     * Route the test admin the way AdminRootController routes real admins, which
     * gives ModelAdmin its $ModelClass parameter (CMSMenu skips TestOnly classes).
     *
     * @return array
     */
    protected function getExtraRoutes()
    {
        return array_merge(parent::getExtraRoutes(), [
            'admin/fluent-gridfield-test//$ModelClass/$Action' => TestAdmin::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->autoFollowRedirection = false;
        $this->logInWithPermission('ADMIN');
    }

    /**
     * Archiving a record in a nested GridField returns to the record that holds the
     * GridField, not to the top-level list (https://github.com/tractorcow-farm/silverstripe-fluent/issues/614)
     */
    public function testArchiveFluentInNestedGridFieldReturnsToParent(): void
    {
        $parent = $this->objFromFixture(ParentRecord::class, 'parent');
        $child = $this->createChild((int) $parent->ID);
        $parentLink = $this->itemLink(ParentRecord::class, (int) $parent->ID);

        $response = $this->archiveFluent(
            Controller::join_links($parentLink, 'ItemEditForm/field/Children/item', $child->ID, 'ItemEditForm')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame($parentLink, $this->redirectPath($response));
        $this->assertNull(Versioned::get_by_stage(ChildRecord::class, Versioned::DRAFT)->byID($child->ID));
    }

    /**
     * Archiving a record in a top-level GridField still returns to its list
     */
    public function testArchiveFluentInTopLevelGridFieldReturnsToList(): void
    {
        $child = $this->createChild(0);

        $response = $this->archiveFluent(
            Controller::join_links($this->itemLink(ChildRecord::class, (int) $child->ID), 'ItemEditForm')
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(
            'admin/fluent-gridfield-test/' . $this->sanitise(ChildRecord::class),
            $this->redirectPath($response)
        );
    }

    private function createChild(int $parentID): ChildRecord
    {
        return FluentState::singleton()->withState(function (FluentState $state) use ($parentID): ChildRecord {
            $state->setLocale('en_US');

            $child = ChildRecord::create();
            $child->Title = 'Child';
            $child->ParentID = $parentID;
            $child->write();
            $child->publishRecursive();

            return $child;
        });
    }

    private function archiveFluent(string $itemEditFormLink): HTTPResponse
    {
        return $this->post($itemEditFormLink, ['action_archiveFluent' => 1]);
    }

    private function itemLink(string $class, int $id): string
    {
        $model = $this->sanitise($class);

        return "admin/fluent-gridfield-test/$model/EditForm/field/$model/item/$id";
    }

    private function sanitise(string $class): string
    {
        return str_replace('\\', '-', $class);
    }

    private function redirectPath(HTTPResponse $response): string
    {
        return trim((string) parse_url((string) $response->getHeader('Location'), PHP_URL_PATH), '/');
    }
}
