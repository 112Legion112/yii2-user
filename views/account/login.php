<?php

use comyii\user\widgets\LoginForm;
use comyii\user\widgets\Logo;
/**
 * @var yii\web\View $this
 * @var comyii\user\models\Login $model
 */
?>
<div class="text-center">
    <?= Logo::widget() ?>
</div>
<div class="y2u-box">
    <?= LoginForm::widget([
        'model' => $model,
        'title' => $loginTitle,
        'hasSocialAuth' => $hasSocialAuth,
        'authAction' => $authAction,
        'authTitle' => $authTitle
    ]); ?>
</div>