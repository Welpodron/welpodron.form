<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UserConsent\Agreement;

$arComponentParameters = [
    'PARAMETERS' => [
        'AGREEMENT_ID' => [
            'NAME' => 'Соглашение',
            'PARENT' => 'BASE',
            'TYPE' => 'LIST',
            'VALUES' => Agreement::getActiveList()
        ],
        'FIELDS' => [
            'NAME' => 'Поля соглашения',
            'PARENT' => 'BASE',
            'TYPE' => 'STRING',
        ],
        'BUTTON_CAPTION' => [
            'NAME' => 'Текст кнопки',
            'PARENT' => 'BASE',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Отправить'
        ],
        'LABEL_CAPTION' => [
            'NAME' => 'Текст подписи',
            'PARENT' => 'BASE',
            'TYPE' => 'STRING',
            'DEFAULT' => 'Я согласен с условиями обработки персональных данных'
        ],
        'CACHE_TIME' => ['DEFAULT' => 36000],
        'CACHE_GROUPS' => [
            'PARENT' => 'CACHE_SETTINGS',
            'NAME' => 'Учитывать права доступа',
            'TYPE' => 'CHECKBOX',
            'DEFAULT' => 'Y'
        ]
    ]
];
