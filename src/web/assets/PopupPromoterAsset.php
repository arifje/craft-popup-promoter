<?php

namespace arifje\craftpopuppromoter\web\assets;

use craft\web\AssetBundle;

class PopupPromoterAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->css = [
            'popup-promoter.css',
        ];
        $this->js = [
            'popup-promoter.iife.js',
        ];

        parent::init();
    }
}
