<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Loader;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;

$moduleId = 'welpodron.feedback'; //обязательно, иначе права доступа не работают!

Loader::includeModule($moduleId);
Loader::includeModule("iblock");

$request = Context::getCurrent()->getRequest();

$dbIblocks = CIBlock::GetList([], ['TYPE' => welpodron_feedback::DEFAULT_IBLOCK_TYPE]);

$arIblocks['-1'] = 'Выберете инфоблок';

while ($arIblock = $dbIblocks->Fetch()) {
    $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock["NAME"];
}

#Описание опций

$arTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Основные настройки',
        'OPTIONS' => [
            [
                'IBLOCK_ID',
                'Инфоблок:',
                Option::get($moduleId, 'IBLOCK_ID'), // selected value
                [
                    'selectbox',
                    $arIblocks
                ]
            ],
            [
                'BANNED_SYMBOLS',
                'Список запрещенных символов (через запятую):',
                Option::get($moduleId, 'BANNED_SYMBOLS'),
                ['textarea']
            ],
        ]
    ],
    [
        'DIV' => 'edit2',
        'TAB' => 'Настройки уведомлений',
        'OPTIONS' => [
            [
                'USE_NOTIFY',
                'Отправлять сообщение об отзыве менеджеру сайта',
                Option::get($moduleId, 'USE_NOTIFY'),
                ['checkbox']
            ],
            [
                'NOTIFY_TYPE',
                'Тип почтового события',
                Option::get($moduleId, 'NOTIFY_TYPE'),
                ['text', 40]
            ],
            [
                'NOTIFY_EMAIL',
                'Email менеджера сайта',
                Option::get($moduleId, 'NOTIFY_EMAIL'),
                ['text', 40]
            ],
        ]
    ],
    [
        'DIV' => 'edit3',
        'TAB' => 'Настройки Google reCAPTCHA v3',
        'OPTIONS' => [
            [
                'USE_CAPTCHA',
                'Использовать Google reCAPTCHA v3',
                Option::get($moduleId, 'USE_CAPTCHA'),
                ['checkbox']
            ],
            [
                'GOOGLE_CAPTCHA_SECRET_KEY',
                'Секретный ключ',
                Option::get($moduleId, 'GOOGLE_CAPTCHA_SECRET_KEY'),
                ['text', 40]
            ],
            [
                'GOOGLE_CAPTCHA_PUBLIC_KEY',
                'Публичный ключ',
                Option::get($moduleId, 'GOOGLE_CAPTCHA_PUBLIC_KEY'),
                ['text', 40]
            ],
        ]
    ],
    [
        'DIV' => 'edit4',
        'TAB' => 'Настройки внешнего вида',
        'OPTIONS' => [
            [
                'SUCCESS_TITLE',
                'Заголовок блока после успешной отправки формы',
                Option::get($moduleId, 'SUCCESS_TITLE'),
                ['textarea', 5]
            ],
            [
                'SUCCESS_CONTENT',
                'Содержимое блока после успешной отправки формы',
                Option::get($moduleId, 'SUCCESS_CONTENT'),
                ['textarea', 5]
            ],
            [
                'SUCCESS_BTN_LABEL',
                'Текст кнопки после успешной отправки формы',
                Option::get($moduleId, 'SUCCESS_BTN_LABEL'),
                ['textarea', 5]
            ],
            [
                'ERROR_TITLE',
                'Заголовок блока при ошибке отправки формы',
                Option::get($moduleId, 'ERROR_TITLE'),
                ['textarea', 5]
            ],
            [
                'ERROR_CONTENT',
                'Содержимое блока при ошибке отправки формы',
                Option::get($moduleId, 'ERROR_CONTENT'),
                ['textarea', 5]
            ],
            [
                'ERROR_BTN_LABEL',
                'Текст кнопки при ошибке отправки формы',
                Option::get($moduleId, 'ERROR_BTN_LABEL'),
                ['textarea', 5]
            ]
        ]
    ],
];

#Сохранение

if ($request->isPost() && $request['save'] && check_bitrix_sessid()) {
    foreach ($arTabs as $arTab) {
        __AdmSettingsSaveOptions($moduleId, $arTab['OPTIONS']);
    }

    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($moduleId) .
        '&tabControl_active_tab=' . urlencode($request['tabControl_active_tab']));
}

#Визуальный вывод

$tabControl = new CAdminTabControl('tabControl', $arTabs);
?>
<form method='post' name='welpodron_form_settings'>
    <? $tabControl->Begin(); ?>
    <? foreach ($arTabs as $arTab) : ?>
        <?
        $tabControl->BeginNextTab();
        __AdmSettingsDrawList($moduleId, $arTab['OPTIONS']);
        ?>
    <? endforeach; ?>
    <? $tabControl->Buttons(['btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false]); ?>
    <?= bitrix_sessid_post(); ?>
    <? $tabControl->End(); ?>
</form>