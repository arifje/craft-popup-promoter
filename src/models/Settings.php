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
    public $sectionHandle = '';
    public $titleFieldHandle = self::TITLE_SOURCE;
    public $descriptionFieldHandle = 'popupDescription';
    public $imageFieldHandle = 'popupImage';
    public $ctaUrlFieldHandle = 'popupCtaUrl';
    public $ctaLabelFieldHandle = 'popupCtaLabel';
    public $defaultVariant = 'center';
    public $ctaLabelDefault = 'Learn more';
    public $ctaTarget = '_self';
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

        $rules[] = [['enabled', 'autoInject', 'closeOnEsc', 'closeOnBackdrop'], 'boolean'];
        $rules[] = [[
            'sectionHandle',
            'titleFieldHandle',
            'descriptionFieldHandle',
            'imageFieldHandle',
            'ctaUrlFieldHandle',
            'ctaLabelFieldHandle',
            'defaultVariant',
            'ctaLabelDefault',
            'ctaTarget',
            'cookieNamePrefix',
        ], 'string'];
        $rules[] = [['delaySeconds'], 'integer', 'min' => 0, 'max' => 86400];
        $rules[] = [['cookieDurationDays'], 'integer', 'min' => 0, 'max' => 3650];
        $rules[] = [['defaultVariant'], 'in', 'range' => array_keys(self::VARIANTS)];
        $rules[] = [['ctaTarget'], 'in', 'range' => ['_self', '_blank']];

        return $rules;
    }
}
