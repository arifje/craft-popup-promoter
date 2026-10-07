<?php

namespace arifje\craftpopuppromoter\services;

use arifje\craftpopuppromoter\Plugin;
use Craft;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\fieldlayoutelements\entries\EntryTitleField;
use craft\fields\Assets;
use craft\fields\Lightswitch;
use craft\fields\PlainText;
use craft\models\EntryType;
use craft\models\FieldGroup;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use yii\base\Component;
use yii\base\Exception;

class DefaultContentService extends Component
{
    public const SECTION_HANDLE = 'popups';

    public function ensureDefaults(): array
    {
        $fields = $this->ensureFields();
        $section = $this->ensureSection();
        $this->ensureFieldLayout($section, $fields);

        $settings = [
            'sectionHandle' => $section->handle,
            'showPopupFieldHandle' => 'popupShow',
            'descriptionFieldHandle' => 'popupDescription',
            'imageFieldHandle' => 'popupImage',
            'ctaUrlFieldHandle' => 'popupCtaUrl',
            'ctaLabelFieldHandle' => 'popupCtaLabel',
            'cancelLabelFieldHandle' => 'popupCancelLabel',
            'promotedLabelFieldHandle' => 'popupPromotedLabel',
            'defaultVariant' => 'center',
        ];

        if (!Craft::$app->getPlugins()->savePluginSettings(Plugin::getInstance(), $settings)) {
            throw new Exception(Craft::t('craft-popup-promoter', 'The popup content model is ready, but its plugin settings could not be saved. Check the settings and retry setup.'));
        }

        return [
            'section' => $section,
            'fields' => $fields,
            'settings' => $settings,
        ];
    }

    /**
     * @return array<string, FieldInterface>
     */
    private function ensureFields(): array
    {
        return [
            'popupShow' => $this->ensureField(Lightswitch::class, 'Show Popup', 'popupShow'),
            'popupDescription' => $this->ensureField(PlainText::class, 'Popup Description', 'popupDescription', [
                'multiline' => true,
                'initialRows' => 4,
            ]),
            'popupImage' => $this->ensureField(Assets::class, 'Popup Image', 'popupImage', [
                'sources' => '*',
                'maxRelations' => 1,
            ]),
            'popupCtaUrl' => $this->ensureField(PlainText::class, 'Popup Call to Action URL', 'popupCtaUrl'),
            'popupCtaLabel' => $this->ensureField(PlainText::class, 'Popup Call to Action Label', 'popupCtaLabel'),
            'popupCancelLabel' => $this->ensureField(PlainText::class, 'Popup Cancel Button Label', 'popupCancelLabel'),
            'popupPromotedLabel' => $this->ensureField(PlainText::class, 'Popup Promoted Label', 'popupPromotedLabel'),
        ];
    }

    private function ensureField(string $class, string $name, string $handle, array $config = []): FieldInterface
    {
        $field = Craft::$app->getFields()->getFieldByHandle($handle);
        if ($field) {
            return $field;
        }

        $field = Craft::createObject(array_merge([
            'class' => $class,
            'name' => $name,
            'handle' => $handle,
        ], $config));

        // Craft 4 requires global fields to belong to a group; Craft 5 removed groups.
        $fieldsService = Craft::$app->getFields();
        if (method_exists($fieldsService, 'getAllGroups')) {
            $group = null;
            foreach ($fieldsService->getAllGroups() as $existingGroup) {
                if ($existingGroup->name === 'Popup Promoter') {
                    $group = $existingGroup;
                    break;
                }
            }

            if (!$group) {
                $group = new FieldGroup(['name' => 'Popup Promoter']);
                if (!$fieldsService->saveGroup($group)) {
                    throw new Exception(Craft::t('craft-popup-promoter', 'Could not save popup field group: {errors}', [
                        'errors' => implode(', ', $group->getErrorSummary(true)),
                    ]));
                }
            }

            $field->groupId = $group->id;
        }

        if (!Craft::$app->getFields()->saveField($field)) {
            throw new Exception(sprintf(
                'Could not save field "%s": %s',
                $handle,
                implode(', ', $field->getErrorSummary(true))
            ));
        }

        return $field;
    }

    private function ensureSection(): Section
    {
        $sectionsService = $this->sectionsService();
        $section = $sectionsService->getSectionByHandle(self::SECTION_HANDLE);
        if ($section) {
            return $section;
        }

        $siteSettings = [];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $siteSettings[$site->id] = new Section_SiteSettings([
                'siteId' => $site->id,
                'enabledByDefault' => true,
                'hasUrls' => false,
                'uriFormat' => null,
                'template' => null,
            ]);
        }

        $section = new Section([
            'name' => 'Popups',
            'handle' => self::SECTION_HANDLE,
            'type' => Section::TYPE_CHANNEL,
            'enableVersioning' => true,
            'siteSettings' => $siteSettings,
        ]);

        // Craft 5 entry types are global and must exist before a section is saved.
        // Reuse the dedicated type when retrying a partially completed setup.
        if (method_exists($sectionsService, 'getEntryTypeByHandle')) {
            $entryType = $sectionsService->getEntryTypeByHandle('popupPromoter');
            if (!$entryType) {
                $entryType = new EntryType([
                    'name' => 'Popup Promoter',
                    'handle' => 'popupPromoter',
                ]);
                $layout = $entryType->getFieldLayout();
                $tab = new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
                $tab->setElements([new EntryTitleField()]);
                $layout->setTabs([$tab]);
                if (!$sectionsService->saveEntryType($entryType)) {
                    throw new Exception(Craft::t('craft-popup-promoter', 'Could not save popup entry type: {errors}', [
                        'errors' => implode(', ', $entryType->getErrorSummary(true)),
                    ]));
                }
            }

            $section->setEntryTypes([$entryType]);
        }

        if (!$sectionsService->saveSection($section)) {
            throw new Exception(sprintf(
                'Could not save section "%s": %s',
                self::SECTION_HANDLE,
                implode(', ', $section->getErrorSummary(true))
            ));
        }

        return $section;
    }

    /**
     * @param array<string, FieldInterface> $fields
     */
    private function ensureFieldLayout(Section $section, array $fields): void
    {
        $entryTypes = $section->getEntryTypes();
        $entryType = reset($entryTypes);

        if (!$entryType) {
            throw new Exception(Craft::t('craft-popup-promoter', 'The popup section has no entry type. Assign an entry type and retry setup.'));
        }

        $fieldLayout = $entryType->getFieldLayout() ?: new FieldLayout([
            'type' => Entry::class,
        ]);

        $existingFieldUids = [];
        foreach ($fieldLayout->getTabs() as $tab) {
            foreach ($tab->getElements() as $element) {
                if ($element instanceof CustomField) {
                    $existingFieldUids[$element->fieldUid] = true;
                }
            }
        }

        $elements = [];
        foreach ($fields as $field) {
            if (!isset($existingFieldUids[$field->uid])) {
                $elements[] = new CustomField($field);
            }
        }

        if (!$elements) {
            return;
        }

        $tab = new FieldLayoutTab([
            'name' => 'Popup Promoter',
            'layout' => $fieldLayout,
        ]);
        $tab->setElements($elements);

        $tabs = $fieldLayout->getTabs();
        $tabs[] = $tab;
        $fieldLayout->setTabs($tabs);
        $entryType->setFieldLayout($fieldLayout);

        if (!$this->sectionsService()->saveEntryType($entryType)) {
            throw new Exception(sprintf(
                'Could not save popup entry type: %s',
                implode(', ', $entryType->getErrorSummary(true))
            ));
        }
    }

    private function sectionsService(): object
    {
        $entries = Craft::$app->getEntries();

        if (method_exists($entries, 'getAllSections')) {
            return $entries;
        }

        return Craft::$app->getSections();
    }
}
