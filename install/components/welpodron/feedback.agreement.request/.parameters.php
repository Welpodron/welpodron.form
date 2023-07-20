<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UserConsent\Agreement;

$arAgreementTemplateInfo = CComponentUtil::GetTemplatesList('welpodron:feedback.agreement');

$arAgreementTemplates = [];

foreach ($arAgreementTemplateInfo as &$arAgreementTemplate) {
    if ('' != $arAgreementTemplate["TEMPLATE"] && '.default' != $arAgreementTemplate["TEMPLATE"])
        $arAgreementTemplateIDs[] = $arAgreementTemplate["TEMPLATE"];
    if (!isset($arAgreementTemplate['TITLE']))
        $arAgreementTemplate['TITLE'] = $arAgreementTemplate['NAME'];
}
unset($arAgreementTemplate);

if (!empty($arAgreementTemplateIDs)) {
    $dbSiteTemplates = CSiteTemplate::GetList(
        [],
        ["ID" => $arAgreementTemplateIDs],
        []
    );
    while ($arSiteTemplate = $dbSiteTemplates->Fetch()) {
        $arSiteTemplateList[$arSiteTemplate['ID']] = $arSiteTemplate['NAME'];
    }
}

foreach ($arAgreementTemplateInfo as &$arAgreementTemplate) {
    $arAgreementTemplates[$arAgreementTemplate['NAME']] = $arAgreementTemplate["TITLE"] . ' (' . ('' != $arAgreementTemplate["TEMPLATE"] && '' != $arSiteTemplateList[$arAgreementTemplate["TEMPLATE"]] ? $arSiteTemplateList[$arAgreementTemplate["TEMPLATE"]] : 'Встроенный шаблон') . ')';;
}
unset($arAgreementTemplate);

$arComponentParameters = [
    'PARAMETERS' => [
        'AGREEMENT_ID' => [
            'NAME' => 'Соглашение',
            'PARENT' => 'BASE',
            'TYPE' => 'LIST',
            'VALUES' => Agreement::getActiveList()
        ],
        'AGREEMENT_TEMPLATE' => [
            'PARENT' => 'BASE',
            'NAME' => 'Шаблон для ajax соглашения',
            "TYPE" => "LIST",
            "VALUES" => $arAgreementTemplates,
            "DEFAULT" => ".default",
            "ADDITIONAL_VALUES" => "Y"
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
