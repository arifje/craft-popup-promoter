<?php

namespace arifje\craftpopuppromoter\models;

use craft\base\Model;

class Settings extends Model
{
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
    public $sectionHandle = '';
    public $titleFieldHandle = '';
    public $descriptionFieldHandle = 'popupDescription';
    public $imageFieldHandle = 'popupImage';
    public $ctaUrlFieldHandle = 'popupCtaUrl';
    public $ctaLabelFieldHandle = 'popupCtaLabel';
    public $closedFieldHandle = 'popupClosed';
    public $variantFieldHandle = 'popupVariant';
    public $defaultVariant = 'center';
    public $ctaLabelDefault = 'Learn more';
    public $ctaTarget = '_self';
    public $imageTransform = '';
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

        $rules[] = [['enabled', 'autoInject', 'closeOnEsc', 'closeOnBackdrop'], 'boolean'];
        $rules[] = [[
            'sectionHandle',
            'titleFieldHandle',
            'descriptionFieldHandle',
            'imageFieldHandle',
            'ctaUrlFieldHandle',
            'ctaLabelFieldHandle',
            'closedFieldHandle',
            'variantFieldHandle',
            'defaultVariant',
            'ctaLabelDefault',
            'ctaTarget',
            'imageTransform',
            'cookieNamePrefix',
        ], 'string'];
        $rules[] = [['cookieDurationDays'], 'integer', 'min' => 0, 'max' => 3650];
        $rules[] = [['defaultVariant'], 'in', 'range' => array_keys(self::VARIANTS)];
        $rules[] = [['ctaTarget'], 'in', 'range' => ['_self', '_blank']];

        return $rules;
    }
}
