<?php

namespace arifje\craftpopuppromoter\services;

use arifje\craftpopuppromoter\models\Settings;
use arifje\craftpopuppromoter\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\models\Section;
use yii\base\Component;

class PopupService extends Component
{
    public function getSectionOptions(): array
    {
        $options = [
            ['label' => 'Select a section', 'value' => ''],
        ];

        foreach ($this->sectionsService()->getAllSections() as $section) {
            $options[] = [
                'label' => sprintf('%s (%s)', $section->name, $section->handle),
                'value' => $section->handle,
            ];
        }

        return $options;
    }

    public function getFieldOptions(?string $sectionHandle = null): array
    {
        $options = [
            ['label' => 'Not mapped', 'value' => ''],
        ];

        foreach ($this->getFieldsForSection($sectionHandle) as $field) {
            $options[] = [
                'label' => sprintf('%s (%s)', $field->name, $field->handle),
                'value' => $field->handle,
            ];
        }

        return $options;
    }

    public function getTitleFieldOptions(?string $sectionHandle = null): array
    {
        return array_merge([
            ['label' => 'Entry title', 'value' => Settings::TITLE_SOURCE],
        ], $this->getFieldOptions($sectionHandle));
    }

    public function getFieldOptionsBySection(bool $includeTitle = false): array
    {
        $fieldOptions = [
            '' => $includeTitle ? $this->getTitleFieldOptions(null) : $this->getFieldOptions(null),
        ];

        foreach ($this->sectionsService()->getAllSections() as $section) {
            $fieldOptions[$section->handle] = $includeTitle
                ? $this->getTitleFieldOptions($section->handle)
                : $this->getFieldOptions($section->handle);
        }

        return $fieldOptions;
    }

    public function getPopupPayload(): ?array
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enabled || !$settings->sectionHandle) {
            return null;
        }

        $entry = $this->pickEntry($settings, true);
        if (!$entry) {
            return null;
        }

        return $this->payloadForEntry($entry, $settings);
    }

    public function getPreviewPayload(array $config): ?array
    {
        $settings = $this->settingsFromConfig($config);

        if (!$settings->sectionHandle) {
            return null;
        }

        $entry = $this->pickEntry($settings, false);
        if (!$entry) {
            return null;
        }

        return $this->payloadForEntry($entry, $settings);
    }

    private function payloadForEntry(Entry $entry, Settings $settings): array
    {
        $title = $this->stringFieldValue($entry, $settings->titleFieldHandle);
        $description = $this->stringFieldValue($entry, $settings->descriptionFieldHandle);
        $image = $this->imagePayload($entry, $settings);
        $ctaUrl = $this->urlFieldValue($entry, $settings->ctaUrlFieldHandle);
        $ctaLabel = $this->stringFieldValue($entry, $settings->ctaLabelFieldHandle) ?: $settings->ctaLabelDefault;
        $variant = $this->variantForSettings($settings);

        return [
            'id' => (int)$entry->id,
            'uid' => (string)$entry->uid,
            'title' => $title ?: (string)$entry->title,
            'description' => $description,
            'image' => $image,
            'cta' => $ctaUrl ? [
                'url' => $ctaUrl,
                'label' => $ctaLabel,
                'target' => $settings->ctaTarget ?: '_self',
            ] : null,
            'variant' => $variant,
            'cookieName' => $this->cookieName($entry, $settings),
            'cookieDurationDays' => (int)$settings->cookieDurationDays,
            'closeOnEsc' => (bool)$settings->closeOnEsc,
            'closeOnBackdrop' => (bool)$settings->closeOnBackdrop,
        ];
    }

    private function pickEntry(Settings $settings, bool $respectDismissalCookies): ?Entry
    {
        try {
            $entries = Entry::find()
                ->section($settings->sectionHandle)
                ->siteId(Craft::$app->getSites()->getCurrentSite()->id)
                ->status('live')
                ->limit(null)
                ->all();
        } catch (\Throwable $exception) {
            Craft::warning(
                sprintf('Could not query popup entries: %s', $exception->getMessage()),
                __METHOD__
            );

            return null;
        }

        if (!$entries) {
            return null;
        }

        shuffle($entries);

        foreach ($entries as $entry) {
            if (
                $entry instanceof Entry
                && (!$respectDismissalCookies || !$this->hasDismissalCookie($entry, $settings))
            ) {
                return $entry;
            }
        }

        return null;
    }

    private function sectionsService(): object
    {
        $entries = Craft::$app->getEntries();

        if (method_exists($entries, 'getAllSections')) {
            return $entries;
        }

        return Craft::$app->getSections();
    }

    private function hasDismissalCookie(Entry $entry, Settings $settings): bool
    {
        return Craft::$app->getRequest()->getCookies()->getValue($this->cookieName($entry, $settings)) !== null;
    }

    private function cookieName(Entry $entry, Settings $settings): string
    {
        $prefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $settings->cookieNamePrefix ?: 'craft_popup_promoter_closed');
        $identifier = $entry->uid ?: $entry->id;

        return $prefix . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$identifier);
    }

    private function variantForSettings(Settings $settings): string
    {
        $variant = $settings->defaultVariant ?: 'center';

        return array_key_exists($variant, Settings::VARIANTS) ? $variant : 'center';
    }

    private function imagePayload(Entry $entry, Settings $settings): ?array
    {
        $value = $this->fieldValue($entry, $settings->imageFieldHandle);
        $asset = $this->firstAsset($value);

        if (!$asset) {
            return null;
        }

        try {
            $url = $asset->getUrl();
        } catch (\Throwable $exception) {
            $url = null;
        }

        if (!$url) {
            return null;
        }

        return [
            'url' => $url,
            'alt' => method_exists($asset, 'getAlt') ? ($asset->getAlt() ?: $asset->title) : $asset->title,
        ];
    }

    private function firstAsset($value): ?Asset
    {
        if ($value instanceof Asset) {
            return $value;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $asset = $this->firstAsset($item);
                if ($asset) {
                    return $asset;
                }
            }
        }

        if (is_object($value) && method_exists($value, 'one')) {
            $element = $value->one();

            return $element instanceof Asset ? $element : null;
        }

        return null;
    }

    private function urlFieldValue(Entry $entry, string $handle): string
    {
        $value = $this->fieldValue($entry, $handle);

        if ($value instanceof ElementInterface && method_exists($value, 'getUrl')) {
            return (string)$value->getUrl();
        }

        if (is_object($value) && method_exists($value, 'one')) {
            $element = $value->one();
            if ($element instanceof ElementInterface && method_exists($element, 'getUrl')) {
                return (string)$element->getUrl();
            }
        }

        return $this->normalizeString($value);
    }

    private function stringFieldValue(Entry $entry, string $handle): string
    {
        return $this->normalizeString($this->fieldValue($entry, $handle));
    }

    private function fieldValue(Entry $entry, string $handle)
    {
        if (!$handle) {
            return null;
        }

        if ($handle === Settings::TITLE_SOURCE || $handle === 'title') {
            return $entry->title;
        }

        try {
            return $entry->getFieldValue($handle);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function normalizeString($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return trim(implode(', ', array_filter(array_map([$this, 'normalizeString'], $value))));
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return trim(strip_tags((string)$value));
            }

            return '';
        }

        return trim(strip_tags((string)$value));
    }

    private function settingsFromConfig(array $config): Settings
    {
        /** @var Settings $currentSettings */
        $currentSettings = Plugin::getInstance()->getSettings();
        $settings = new Settings();

        foreach ($this->settingKeys() as $key) {
            $settings->$key = $currentSettings->$key;
        }

        foreach ($this->settingKeys() as $key) {
            if (array_key_exists($key, $config)) {
                $settings->$key = $config[$key];
            }
        }

        foreach (['enabled', 'autoInject', 'closeOnEsc', 'closeOnBackdrop'] as $key) {
            $settings->$key = filter_var($settings->$key, FILTER_VALIDATE_BOOLEAN);
        }

        $settings->cookieDurationDays = (int)$settings->cookieDurationDays;

        return $settings;
    }

    private function settingKeys(): array
    {
        return [
            'enabled',
            'autoInject',
            'sectionHandle',
            'titleFieldHandle',
            'descriptionFieldHandle',
            'imageFieldHandle',
            'ctaUrlFieldHandle',
            'ctaLabelFieldHandle',
            'defaultVariant',
            'ctaLabelDefault',
            'ctaTarget',
            'cookieDurationDays',
            'cookieNamePrefix',
            'closeOnEsc',
            'closeOnBackdrop',
        ];
    }

    /**
     * @return FieldInterface[]
     */
    private function getFieldsForSection(?string $sectionHandle): array
    {
        if (!$sectionHandle) {
            return [];
        }

        $section = $this->sectionsService()->getSectionByHandle($sectionHandle);
        if (!$section) {
            return [];
        }

        $fields = [];

        foreach ($this->getEntryTypesForSection($section) as $entryType) {
            $fieldLayout = $entryType->getFieldLayout();
            if (!$fieldLayout) {
                continue;
            }

            foreach ($fieldLayout->getCustomFields() as $field) {
                $fields[$field->handle] = $field;
            }
        }

        uasort($fields, static fn(FieldInterface $a, FieldInterface $b): int => strcasecmp($a->name, $b->name));

        return array_values($fields);
    }

    private function getEntryTypesForSection(Section $section): array
    {
        if (method_exists($section, 'getEntryTypes')) {
            return $section->getEntryTypes();
        }

        $sectionsService = $this->sectionsService();
        if (method_exists($sectionsService, 'getEntryTypesBySectionId')) {
            return $sectionsService->getEntryTypesBySectionId($section->id);
        }

        return [];
    }
}
