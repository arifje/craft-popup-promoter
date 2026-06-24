<?php

namespace arifje\craftpopuppromoter;

use arifje\craftpopuppromoter\models\Settings;
use arifje\craftpopuppromoter\services\DefaultContentService;
use arifje\craftpopuppromoter\services\PopupService;
use arifje\craftpopuppromoter\variables\PopupPromoterVariable;
use arifje\craftpopuppromoter\web\assets\PopupPromoterAsset;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\console\Application as ConsoleApplication;
use craft\helpers\Json;
use craft\helpers\UrlHelper;
use craft\web\twig\variables\CraftVariable;
use craft\web\View;
use yii\base\Event;

class Plugin extends BasePlugin
{
    public static Plugin $plugin;

    public bool $hasCpSettings = true;
    public string $schemaVersion = '1.0.0';

    private bool $frontendConfigRegistered = false;
    private bool $frontendAssetsRegistered = false;

    public function init(): void
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'defaultContent' => DefaultContentService::class,
            'popups' => PopupService::class,
        ]);

        $this->registerCraftVariable();
        $this->registerAutomaticFrontendAssets();
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        /** @var Settings $settings */
        $settings = $this->getSettings();

        return Craft::$app->getView()->renderTemplate('craft-popup-promoter/settings', [
            'settings' => $settings,
            'sections' => $this->popups->getSectionOptions(),
            'fields' => $this->popups->getFieldOptions($settings->sectionHandle),
            'titleFields' => $this->popups->getTitleFieldOptions($settings->sectionHandle),
            'fieldsBySection' => $this->popups->getFieldOptionsBySection(),
            'titleFieldsBySection' => $this->popups->getFieldOptionsBySection(true),
            'variants' => Settings::variantOptions(),
            'installDefaultsAction' => UrlHelper::actionUrl('craft-popup-promoter/setup/install-defaults'),
            'previewAction' => UrlHelper::actionUrl('craft-popup-promoter/setup/preview'),
        ]);
    }

    public function registerFrontendAssets(): void
    {
        if ($this->frontendAssetsRegistered || Craft::$app instanceof ConsoleApplication) {
            return;
        }

        $settings = $this->getSettings();
        if (!$settings->enabled) {
            return;
        }

        $this->registerFrontendConfig();

        if (!$settings->loadDefaultAssets) {
            $this->frontendAssetsRegistered = true;

            return;
        }

        Craft::$app->getView()->registerAssetBundle(PopupPromoterAsset::class);

        $this->frontendAssetsRegistered = true;
    }

    public function registerFrontendConfig(): void
    {
        if ($this->frontendConfigRegistered || Craft::$app instanceof ConsoleApplication) {
            return;
        }

        $settings = $this->getSettings();
        if (!$settings->enabled) {
            return;
        }

        $view = Craft::$app->getView();
        $view->registerJs(
            'window.CraftPopupPromoterConfig = ' . Json::encode([
                'endpoint' => $this->popupEndpointUrl(),
            ]) . ';',
            View::POS_HEAD,
            'craft-popup-promoter-config'
        );

        $this->frontendConfigRegistered = true;
    }

    public function popupEndpointUrl(): string
    {
        return UrlHelper::actionUrl('craft-popup-promoter/popup/data');
    }

    private function registerCraftVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event): void {
                $event->sender->set('popupPromoter', PopupPromoterVariable::class);
            }
        );
    }

    private function registerAutomaticFrontendAssets(): void
    {
        Event::on(
            View::class,
            View::EVENT_BEFORE_RENDER_TEMPLATE,
            function (): void {
                if ($this->shouldRegisterSiteAssets()) {
                    $this->registerFrontendAssets();
                }
            }
        );
    }

    private function shouldRegisterSiteAssets(): bool
    {
        if (Craft::$app instanceof ConsoleApplication) {
            return false;
        }

        $settings = $this->getSettings();
        if (!$settings->enabled || !$settings->autoInject) {
            return false;
        }

        $request = Craft::$app->getRequest();

        return $request->getIsSiteRequest() && !$request->getIsActionRequest();
    }
}
