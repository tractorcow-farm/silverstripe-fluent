<?php


namespace TractorCow\Fluent\Tests\Task\FluentMigrationTaskTest;


class TranslatedDataObjectPartialSubclass extends TranslatedDataObject
{
    private static $db = [
        'Category' => 'Varchar',
        'Colour' => 'Varchar',
    ];

    private static $table_name = 'FluentTestDataObjectPartialSubclass';

    /**
     * @var array
     */
    private static $old_fluent_fields = [
        'Category_en_US',
        'Category_de_AT',
    ];
}
