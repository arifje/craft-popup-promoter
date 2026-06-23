<?php

namespace arifje\craftpopuppromoter\console\controllers;

use arifje\craftpopuppromoter\Plugin;
use yii\console\Controller;
use yii\console\ExitCode;

class SetupController extends Controller
{
    public $defaultAction = 'install-defaults';

    public function actionInstallDefaults(): int
    {
        $result = Plugin::getInstance()->defaultContent->ensureDefaults();

        $this->stdout(sprintf(
            "Default popup section \"%s\" and fields are ready.\n",
            $result['section']->handle
        ));

        return ExitCode::OK;
    }
}
