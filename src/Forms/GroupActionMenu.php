<?php

namespace TractorCow\Fluent\Forms;

use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridField_ActionMenu;
use SilverStripe\Forms\GridField\GridField_ActionMenuItem;
use SilverStripe\Forms\GridField\GridField_ActionMenuLink;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;
use TractorCow\Fluent\Model\Locale;

/**
 * Menu grouped by item group
 */
class GroupActionMenu extends GridField_ActionMenu
{
    protected $group = null;

    /**
     * If set, show a custom header title
     *
     * @var ?string
     */
    protected $customTitle = null;

    public function __construct($group = GridField_ActionMenuItem::DEFAULT_GROUP, $customTitle = null)
    {
        $this->group = $group;
        $this->customTitle = $customTitle;
    }

    /**
     * Get details for this locale against the parent record
     *
     * @param GridField $gridField
     * @param Locale $record Record this group applies to
     * @param string $columnName
     * @return DBHTMLText
     */
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

            $extraData = $item->getExtraData($gridField, $record, $columnName);

            if (array_key_exists('disabled', $extraData) && $extraData['disabled']) {
                // Skip disabled components (likely disabled because of permissions)
                continue;
            }

            $schema[] = [
                'type'  => $item instanceof GridField_ActionMenuLink ? 'link' : 'submit',
                'title' => $item->getTitle($gridField, $record, $columnName),
                'url'   => $item instanceof GridField_ActionMenuLink
                    ? $item->getUrl($gridField, $record, $columnName)
                    : null,
                'group' => $this->group,
                'data'  => $extraData,
            ];
        }

        // Show title, but only if we have at least one item
        if ($schema && $this->customTitle) {
            array_unshift($schema, [
                'type'  => 'link',
                'title' => str_replace('{locale}', $record->getLongTitle(), $this->customTitle),
                'url'   => '#',
                'group' => $this->group,
                'data'  => [
                    'classNames" => "no-js',
                ],
            ]);
        }

        $templateData = ArrayData::create([
            'Schema'     => json_encode($schema),
            'extraClass' => 'action-group action-group-' . $this->group,
        ]);

        $template = SSViewer::get_templates_by_class($this, '');

        return $templateData->renderWith($template);
    }
}
