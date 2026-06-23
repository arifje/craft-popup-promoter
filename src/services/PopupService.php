<?php

namespace arifje\craftpopuppromoter\services;

use arifje\craftpopuppromoter\models\Settings;
use arifje\craftpopuppromoter\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\Entry;
use yii\base\Component;

class PopupService extends Component
{
    public function getSectionOptions(): array
    {
        $options = [
            ['label' => 'Select a section', 'value' => ''],
        ];

        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $options[] = [
                'label' => sprintf('%s (%s)', $section->name, $section->handle),
                'value' => $section->handle,
            ];
        }

        return $options;
    }

    public function getFieldOptions(): array
    {
        $options = [
            ['label' => 'Not mapped', 'value' => ''],
        ];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            $options[] = [
                'label' => sprintf('%s (%s)', $field->name, $field->handle),
                'value' => $field->handle,
            ];
        }

        return $options;
    }

    public function getPopupPayload(): ?array
    {
        /** @var Settings $settings */
        $settings = Plugin::getInstance()->getSettings();

        if (!$settings->enabled || !$settings->sectionHandle) {
            return null;
        }

        $entry = $this->pickEntry($settings);
        if (!$entry) {
            return null;
        }

        $title = $this->stringFieldValue($entry, $settings->titleFieldHandle);
        $description = $this->stringFieldValue($entry, $settings->descriptionFieldHandle);
        $image = $this->imagePayload($entry, $settings);
        $ctaUrl = $this->urlFieldValue($entry, $settings->ctaUrlFieldHandle);
        $ctaLabel = $this->stringFieldValue($entry, $settings->ctaLabelFieldHandle) ?: $settings->ctaLabelDefault;
        $variant = $this->variantForEntry($entry, $settings);

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

    private function pickEntry(Settings $settings): ?Entry
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
                && !$this->isEntryClosed($entry, $settings)
                && !$this->hasDismissalCookie($entry, $settings)
            ) {
                return $entry;
            }
        }

        return null;
    }

    private function isEntryClosed(Entry $entry, Settings $settings): bool
    {
        if (!$settings->closedFieldHandle) {
            return false;
        }

        return (bool)$this->fieldValue($entry, $settings->closedFieldHandle);
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

    private function variantForEntry(Entry $entry, Settings $settings): string
    {
        $variant = $this->stringFieldValue($entry, $settings->variantFieldHandle) ?: $settings->defaultVariant;

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
            $url = $settings->imageTransform ? $asset->getUrl($settings->imageTransform) : $asset->getUrl();
        } catch (\Throwable $exception) {
            $url = $asset->getUrl();
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
}
