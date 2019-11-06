<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionMenuLink;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

/**
 * Menu grouped by item group
 */
class GroupActionMenu extends GridField_ActionMenu
{
    protected $group = null;

    public function __construct($group = GridField_ActionMenuItem::DEFAULT_GROUP)
    {
        $this->group = $group;
    }

    public function getColumnContent($gridField, $record, $columnName)
    {
        $items = $this->getItems($gridField);

        if (!$items) {
            return null;
        }

        $schema = [];
        /* @var GridField_ActionMenuItem $item */
        foreach ($items as $item) {
            // Get items filtered by group
            $group = $item->getGroup($gridField, $record, $columnName);
            if ($group !== $this->group) {
                continue;
            }
            $schema[] = [
                'type'  => $item instanceof GridField_ActionMenuLink ? 'link' : 'submit',
                'title' => $item->getTitle($gridField, $record, $columnName),
                'url'   => $item instanceof GridField_ActionMenuLink ? $item->getUrl($gridField, $record, $columnName) : null,
                'group' => $group,
                'data'  => $item->getExtraData($gridField, $record, $columnName),
            ];
        }

        $templateData = ArrayData::create([
            'Schema'     => json_encode($schema),
            'extraClass' => 'action-group action-group-' . $this->group,
        ]);

        $template = SSViewer::get_templates_by_class($this, '');

        return $templateData->renderWith($template);
    }
}
