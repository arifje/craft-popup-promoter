<?php

namespace arifje\craftpopuppromoter\controllers;

use arifje\craftpopuppromoter\Plugin;
use craft\web\Controller;
use yii\base\Action;
use yii\web\Response;

class PopupController extends Controller
{
    protected array|bool|int $allowAnonymous = ['data'];

    public function beforeAction($action): bool
    {
        if ($action instanceof Action && $action->id === 'data') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionData(): Response
    {
        $this->requireAcceptsJson();

        return $this->asJson([
            'success' => true,
            'popup' => Plugin::getInstance()->popups->getPopupPayload(),
        ]);
    }
}
