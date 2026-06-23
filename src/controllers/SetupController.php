<?php

namespace arifje\craftpopuppromoter\controllers;

use arifje\craftpopuppromoter\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class SetupController extends Controller
{
    protected array|bool|int $allowAnonymous = false;

    public function actionInstallDefaults(): Response
    {
        $this->requireCpRequest();
        $this->requirePostRequest();
        $this->requirePermission('settings');

        try {
            $result = Plugin::getInstance()->defaultContent->ensureDefaults();
        } catch (\Throwable $exception) {
            Craft::error($exception->getMessage(), __METHOD__);

            return $this->asJson([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->asJson([
            'success' => true,
            'message' => sprintf(
                'Default popup section "%s" and fields are ready.',
                $result['section']->handle
            ),
            'sectionHandle' => $result['section']->handle,
        ]);
    }
}
