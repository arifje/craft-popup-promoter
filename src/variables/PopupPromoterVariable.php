<?php

namespace arifje\craftpopuppromoter\variables;

use arifje\craftpopuppromoter\Plugin;

class PopupPromoterVariable
{
    public function register(): string
    {
        Plugin::getInstance()->registerFrontendAssets();

        return '';
    }

    public function payload(): ?array
    {
        return Plugin::getInstance()->popups->getPopupPayload();
    }
}
