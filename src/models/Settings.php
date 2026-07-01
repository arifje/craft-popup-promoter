<?php

namespace arifje\craftpopuppromoter\models;

use craft\base\Model;

class Settings extends Model
{
    public const TITLE_SOURCE = '__title__';

    public const VARIANTS = [
        'center' => 'Centered modal',
        'full' => 'Full page modal',
        'top' => 'Top banner',
        'bottom' => 'Bottom banner',
        'left' => 'Left drawer',
        'right' => 'Right drawer',
        'corner' => 'Corner modal',
    ];

    public $enabled = true;
    public $autoInject = true;
    public $loadDefaultAssets = true;
    public $sectionHandle = '';
    public $titleFieldHandle = self::TITLE_SOURCE;
    public $showPopupFieldHandle = '';
    public $descriptionFieldHandle = 'popupDescription';
    public $imageFieldHandle = 'popupImage';
    public $ctaUrlFieldHandle = 'popupCtaUrl';
    public $ctaLabelFieldHandle = 'popupCtaLabel';
    public $cancelLabelFieldHandle = 'popupCancelLabel';
    public $promotedLabelFieldHandle = 'popupPromotedLabel';
    public $defaultVariant = 'center';
    public $randomizeVariants = false;
    public $promotedLabelDefault = 'Promoted';
    public $ctaLabelDefault = 'Learn more';
    public $cancelLabelDefault = 'No thanks';
    public $ctaTarget = '_self';
    public $buttonColor = '#2563eb';
    public $cancelButtonColor = '#6b7280';
    public $delaySeconds = 0;
    public $cookieDurationDays = 30;
    public $cookieNamePrefix = 'craft_popup_promoter_closed';
    public $closeOnEsc = true;
    public $closeOnBackdrop = true;

    public static function variantOptions(): array
    {
        $options = [];

        foreach (self::VARIANTS as $value => $label) {
            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $options;
    }

    protected function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['enabled', 'autoInject', 'loadDefaultAssets', 'closeOnEsc', 'closeOnBackdrop', 'randomizeVariants'], 'boolean'];
        $rules[] = [[
            'sectionHandle',
            'titleFieldHandle',
            'showPopupFieldHandle',
            'descriptionFieldHandle',
            'imageFieldHandle',
            'ctaUrlFieldHandle',
            'ctaLabelFieldHandle',
            'cancelLabelFieldHandle',
            'promotedLabelFieldHandle',
            'defaultVariant',
            'promotedLabelDefault',
            'ctaLabelDefault',
            'cancelLabelDefault',
            'ctaTarget',
            'buttonColor',
            'cancelButtonColor',
            'cookieNamePrefix',
        ], 'string'];
        $rules[] = [['buttonColor', 'cancelButtonColor'], 'match', 'pattern' => '/^#[0-9a-fA-F]{6}$/'];
        $rules[] = [['delaySeconds'], 'integer', 'min' => 0, 'max' => 86400];
        $rules[] = [['cookieDurationDays'], 'integer', 'min' => 0, 'max' => 3650];
        $rules[] = [['defaultVariant'], 'in', 'range' => array_keys(self::VARIANTS)];
        $rules[] = [['ctaTarget'], 'in', 'range' => ['_self', '_blank']];

        return $rules;
    }
}
