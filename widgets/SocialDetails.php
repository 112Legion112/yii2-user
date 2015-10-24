<?php

namespace comyii\user\widgets;

use Yii;
use yii\bootstrap\Widget;
use kartik\detail\DetailView;

class SocialDetails extends Widget
{
    public $social;
    public $profile;
    public $attributes = [];
    public $widgetOptions = [];
    
    
    public function init() {
        parent::init();
        $m = Yii::$app->getModule('user');
        if(!$m->socialSettings['enabled']) return;
        
        $attributes = [
            [
                'group' => true,
                'label' => $m->icon('globe') . Yii::t('user', 'Social Details'),
                'rowOptions' => ['class'=>'info']
            ],
        ];
        if (count($this->social) === 1 && $this->social[0]->isNewRecord) {
            $attributes[] = [
                'group' => true,
                'label' => '<span class="not-set" style="font-weight:normal">' . Yii::t('user', 'No social profiles linked yet') . '</span>'
            ];
        } else {
            foreach($this->social as $record) {
                $provider = empty($record->source) ? Yii::t('user', 'Unknown') : '<span class="auth-icon ' . $record->source . '"></span>' .
                    '<span class="auth-title">' . ucfirst($record->source) . '</span>';
                $attributes[] = [
                    'label' => Yii::t('user', 'Source'),
                    'value' => '<b>' . Yii::t('user', 'Connected On') . '</b>',
                    'format' => 'raw',
                    'rowOptions' => ['class'=>'active'],
                    'labelColOptions' => ['style' => 'width:90px;text-align:center']
                ];
                $attributes[] = [
                    'label' => '<div class="auth-client"><span class="auth-link">' . $provider . '</span></div>',
                    'value' => $record->updated_on,
                    'labelColOptions' => ['style' => 'text-align:center;vertical-align:middle'],
                    'valueColOptions' => ['style' => 'vertical-align:middle'],
                    'format' => ['datetime', $m->datetimeDispFormat]
                ];
            }
        }
        
        $this->attributes = array_replace_recursive($attributes, $this->attributes);
        
        $this->widgetOptions = array_replace_recursive([
            'striped' => false,
            'hover' => true, 
            'enableEditMode' => false,
            'attributes' => $this->attributes
        ],$this->widgetOptions);
        
        $this->widgetOptions['model'] = $this->profile;
    }
    
    public function run() {
        if(!Yii::$app->getModule('user')->socialSettings['enabled']) return;
        echo DetailView::widget($this->widgetOptions);

    }
}