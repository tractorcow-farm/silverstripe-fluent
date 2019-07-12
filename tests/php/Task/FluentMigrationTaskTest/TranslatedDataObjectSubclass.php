<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


class TranslatedDataObjectSubclass extends TranslatedDataObject
{
    private static $db = [
        'Category' => 'Varchar'
    ];

    private static $table_name = 'FluentTestDataObjectSubclass';

    /**
     * @var array
     */
    private static $old_fluent_fields = [
        'Category_en_US',
        'Category_de_AT',
    ];
}
