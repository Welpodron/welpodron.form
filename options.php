<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\Context;

Loader::includeModule("iblock");

$moduleId = 'welpodron.feedback';

$dbIblocks = CIBlock::GetList();

$arIblocks['-1'] = 'Выберете инфоблок';

while ($arIblock = $dbIblocks->Fetch()) {
    $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock["NAME"];
}

// v2 убрано только 1 определенное почтовое событие, теперь можно выбрать любое
$dbMailEvents = CEventType::GetList();
$arMailEvents['-1'] = 'Выберете почтовое событие';

while ($arMailEvent = $dbMailEvents->Fetch()) {
    $arMailEvents[$arMailEvent['EVENT_NAME']] = '[' . $arMailEvent['EVENT_NAME'] . '] ' . $arMailEvent["NAME"];
}

$arTabs = [
    [
        'DIV' => 'edit1',
        'TAB' => 'Основные настройки',
        'TITLE' => 'Основные настройки',
        'GROUPS' => [
            [
                'TITLE' => 'Сохранение данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_SAVE',
                        'LABEL' => 'Сохранять данные в инфоблок',
                        'VALUE' => Option::get($moduleId, 'USE_SAVE'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'IBLOCK_ID',
                        'LABEL' => 'Инфоблок',
                        'VALUE' => Option::get($moduleId, 'IBLOCK_ID'),
                        'TYPE' => 'selectbox',
                        'OPTIONS' => $arIblocks,
                    ],
                ],
            ],
            [
                'TITLE' => 'Валидация данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'BANNED_SYMBOLS',
                        'LABEL' => 'Список запрещенных символов/слов (через запятую)',
                        'VALUE' => Option::get($moduleId, 'BANNED_SYMBOLS'),
                        'TYPE' => 'textarea',
                    ],
                ],
            ],
            [
                'TITLE' => 'Согласие на обработку персональных данных',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_AGREEMENT_CHECK',
                        'LABEL' => 'Проверять в данных, пришедших с клиента, наличие согласия на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'USE_AGREEMENT_CHECK'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'LABEL' => 'Код свойства в котором хранится согласие пользователя на обработку персональных данных не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'AGREEMENT_CHECK_PROPERTY',
                        'LABEL' => 'Код свойства в котором хранится согласие на обработку персональных данных',
                        'VALUE' => Option::get($moduleId, 'AGREEMENT_CHECK_PROPERTY'),
                        'TYPE' => 'text',
                    ],
                    [
                        'LABEL' => 'Код свойства в котором хранится ID пользовательского соглашения не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента. Данный чекбокс позволяет сохранять информацию в список согласий пользователя.<br><br><em>Работает только с включенной опцией "Проверять наличие в данных, пришедших с клиента, наличие согласия на обработку персональных данных"</em>',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'AGREEMENT_ID_PROPERTY',
                        'LABEL' => 'Код свойства в котором хранится ID пользовательского соглашения',
                        'VALUE' => Option::get($moduleId, 'AGREEMENT_ID_PROPERTY'),
                        'TYPE' => 'text',
                    ],
                ],
            ],
        ],
    ],
    [
        'DIV' => 'edit2',
        'TAB' => 'Настройки уведомлений',
        'TITLE' => 'Настройки уведомлений',
        'GROUPS' => [
            [
                'TITLE' => 'Уведомления менеджеру сайта',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_NOTIFY',
                        'LABEL' => 'Отправлять уведомления менеджеру сайта',
                        'VALUE' => Option::get($moduleId, 'USE_NOTIFY'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'NOTIFY_TYPE',
                        'LABEL' => 'Тип почтового события',
                        'VALUE' => Option::get($moduleId, 'NOTIFY_TYPE'),
                        'TYPE' => 'selectbox',
                        'OPTIONS' => $arMailEvents,
                    ],
                    [
                        'NAME' => 'NOTIFY_EMAIL',
                        'LABEL' => 'Email менеджера сайта',
                        'VALUE' => Option::get($moduleId, 'NOTIFY_EMAIL'),
                        'TYPE' => 'text',
                    ],
                ],
            ],
            [
                'TITLE' => 'Уведомления пользователю заполнившему форму',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_RETURN_NOTIFY',
                        'LABEL' => 'Отправлять уведомления пользователю заполнившему форму',
                        'VALUE' => Option::get($moduleId, 'USE_RETURN_NOTIFY'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'RETURN_NOTIFY_TYPE',
                        'LABEL' => 'Тип почтового события',
                        'VALUE' => Option::get($moduleId, 'RETURN_NOTIFY_TYPE'),
                        'TYPE' => 'selectbox',
                        'OPTIONS' => $arMailEvents,
                    ],
                    [
                        'LABEL' => 'Код свойства в котором хранится email пользователя не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'RETURN_NOTIFY_PROPERTY',
                        'LABEL' => 'Код свойства в котором хранится email пользователя',
                        'VALUE' => Option::get($moduleId, 'RETURN_NOTIFY_PROPERTY'),
                        'TYPE' => 'text',
                    ],
                ],
            ],
        ],
    ],
    [
        'DIV' => 'edit3',
        'TAB' => 'Настройки Google reCAPTCHA v3',
        'TITLE' => 'Настройки Google reCAPTCHA v3',
        'GROUPS' => [
            [
                'TITLE' => 'Настройки Google reCAPTCHA v3',
                'OPTIONS' => [
                    [
                        'NAME' => 'USE_CAPTCHA',
                        'LABEL' => 'Использовать Google reCAPTCHA v3',
                        'VALUE' => Option::get($moduleId, 'USE_CAPTCHA'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_SECRET_KEY',
                        'LABEL' => 'Секретный ключ',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_SECRET_KEY'),
                        'TYPE' => 'text',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_PUBLIC_KEY',
                        'LABEL' => 'Публичный ключ (ключ сайта)',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_PUBLIC_KEY'),
                        'TYPE' => 'text',
                    ],
                ],
            ]
        ]
    ],
    //! TODO: v2 Внешний вид ответа теперь регламентируется компонента, а не настройками модуля 
    [
        'DIV' => 'edit4',
        'TAB' => 'Настройки внешнего вида ответа',
        'TITLE' => 'Настройки внешнего вида ответа',
        'GROUPS' => [
            [
                'TITLE' => 'Настройки внешнего вида ответа',
                'OPTIONS' => [
                    [
                        'NAME' => 'SUCCESS_FILE',
                        'LABEL' => 'PHP файл-шаблон успешного ответа',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_FILE'),
                        'TYPE' => 'file',
                    ],
                    [
                        'NAME' => 'ERROR_FILE',
                        'LABEL' => 'PHP файл-шаблон ответа с ошибкой',
                        'VALUE' => Option::get($moduleId, 'ERROR_FILE'),
                        'TYPE' => 'file',
                    ],
                    [
                        'LABEL' => 'Рекомендуется использовать PHP файл-шаблон успешного ответа. Если PHP файл-шаблон успешного ответа не задан, то будет использоваться содержимое успешного ответа по умолчанию',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'SUCCESS_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое успешного ответа по умолчанию',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                    ],
                    [
                        'LABEL' => 'Рекомендуется использовать PHP файл-шаблон ответа с ошибкой. Если PHP файл-шаблон ответа с ошибкой не задан, то будет использоваться содержимое ответа с ошибкой по умолчанию',
                        'TYPE' => 'note'
                    ],
                    [
                        'NAME' => 'ERROR_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое ответа с ошибкой по умолчанию',
                        'VALUE' => Option::get($moduleId, 'ERROR_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                    ],
                ],
            ]
        ]
    ],
];

$request = Context::getCurrent()->getRequest();

if ($request->isPost() && $request['save'] && check_bitrix_sessid()) {
    foreach ($arTabs as $arTab) {
        foreach ($arTab['GROUPS'] as $arGroup) {
            foreach ($arGroup['OPTIONS'] as $arOption) {
                if ($arOption['TYPE'] == 'note') continue;

                $value = $request->getPost($arOption['NAME']);

                if ($arOption['TYPE'] == "checkbox" && $value != "Y") {
                    $value = "N";
                } elseif (is_array($value)) {
                    $value = implode(",", $value);
                } elseif ($value === null) {
                    $value = '';
                }

                Option::set($moduleId, $arOption['NAME'], $value);
            }
        }
    }

    LocalRedirect($APPLICATION->GetCurPage() . '?lang=' . LANGUAGE_ID . '&mid_menu=1&mid=' . urlencode($moduleId) .
        '&tabControl_active_tab=' . urlencode($request['tabControl_active_tab']));
}

$tabControl = new CAdminTabControl("tabControl", $arTabs, true, true);
?>

<form name=<?= str_replace('.', '_', $moduleId) ?> method='post'>
    <? $tabControl->Begin(); ?>
    <?= bitrix_sessid_post(); ?>
    <? foreach ($arTabs as $arTab) : ?>
        <? $tabControl->BeginNextTab(); ?>
        <? foreach ($arTab['GROUPS'] as $arGroup) : ?>
            <tr class="heading">
                <td colspan="2"><?= $arGroup['TITLE'] ?></td>
            </tr>
            <? foreach ($arGroup['OPTIONS'] as $arOption) : ?>
                <tr>
                    <td style="width: 40%;">
                        <? if ($arOption['TYPE'] != 'note') : ?>
                            <label for="<?= $arOption['NAME'] ?>">
                                <?= $arOption['LABEL'] ?>
                            </label>
                        <? endif ?>
                    </td>
                    <td>
                        <? if ($arOption['TYPE'] == 'note') : ?>
                            <div class="adm-info-message">
                                <?= $arOption['LABEL'] ?>
                            </div>
                        <? elseif ($arOption['TYPE'] == 'checkbox') : ?>
                            <input <? if ($arOption['VALUE'] == "Y") echo "checked "; ?> type="checkbox" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" value="Y">
                        <? elseif ($arOption['TYPE'] == 'textarea') : ?>
                            <textarea id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" rows="5" cols="80" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>"><?= $arOption['VALUE'] ?></textarea>
                        <? elseif ($arOption['TYPE'] == 'selectbox') : ?>
                            <select id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>">
                                <? foreach ($arOption['OPTIONS'] as $key => $value) : ?>
                                    <option <? if ($arOption['VALUE'] == $key) echo "selected "; ?> value="<?= $key ?>"><?= $value ?></option>
                                <? endforeach; ?>
                            </select>
                        <? elseif ($arOption['TYPE'] == 'file') : ?>
                            <?
                            CAdminFileDialog::ShowScript(
                                array(
                                    "event" => str_replace('_', '', 'browsePath' . htmlspecialcharsbx($arOption['NAME'])),
                                    "arResultDest" => array("FORM_NAME" => str_replace('.', '_', $moduleId), "FORM_ELEMENT_NAME" => $arOption['NAME']),
                                    "arPath" => array("PATH" => GetDirPath($arOption['VALUE'])),
                                    "select" => 'F', // F - file only, D - folder only
                                    "operation" => 'O', // O - open, S - save
                                    "showUploadTab" => false,
                                    "showAddToMenuTab" => false,
                                    "fileFilter" => 'php',
                                    "allowAllFiles" => true,
                                    "SaveConfig" => true,
                                )
                            );
                            ?>
                            <input type="text" id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" size="80" maxlength="255" value="<?= htmlspecialcharsbx($arOption['VALUE']); ?>">&nbsp;<input type="button" name="<?= ('browse' . htmlspecialcharsbx($arOption['NAME'])) ?>" value="..." onClick="<?= (str_replace('_', '', 'browsePath' . htmlspecialcharsbx($arOption['NAME']))) ?>()">
                        <? elseif ($arOption['TYPE'] == 'editor') : ?>
                            <textarea id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" rows="5" cols="80" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>"><?= $arOption['VALUE'] ?></textarea>
                            <?
                            //! TODO: v3 Возможно стоит использовать либо AddHTMLEditorFrame либо CLightHTMLEditor либо \Bitrix\Fileman\Block\Editor 

                            // if (CModule::IncludeModule("fileman")) {
                            //     global $USER;

                            //     $isUserHavePhpAccess = $USER->CanDoOperation('edit_php');

                            //     $optionValue = $arOption['VALUE'];

                            //     if (!$isUserHavePhpAccess) {
                            //         $optionValue = htmlspecialcharsbx(LPA::PrepareContent(htmlspecialcharsback($optionValue)));
                            //     }

                            //     CFileMan::AddHTMLEditorFrame(
                            //         "MESSAGE",
                            //         $optionValue,
                            //         "BODY_TYPE",
                            //         '',
                            //         array(
                            //             'height' => 450,
                            //             'width' => '100%'
                            //         ),
                            //         "N",
                            //         0,
                            //         "",
                            //         "onfocus=\"t=this\"",
                            //         false,
                            //         !$isUserHavePhpAccess,
                            //         false,
                            //         array(
                            //             //'saveEditorKey' => $IBLOCK_ID,
                            //             //'site_template_type' => 'mail',
                            //             'templateID' => '',
                            //             'componentFilter' => array('TYPE' => 'mail'),
                            //             'limit_php_access' => !$isUserHavePhpAccess
                            //         )
                            //     );
                            // } else {
                            // }
                            ?>
                        <? else : ?>
                            <input id="<?= htmlspecialcharsbx($arOption['NAME']) ?>" name="<?= htmlspecialcharsbx($arOption['NAME']) ?>" type="text" size="80" maxlength="255" value="<?= $arOption['VALUE'] ?>">
                        <? endif; ?>
                    </td>
                </tr>
            <? endforeach; ?>
        <? endforeach; ?>
    <? endforeach; ?>
    <? $tabControl->Buttons(['btnApply' => false, 'btnCancel' => false, 'btnSaveAndAdd' => false]); ?>
    <? $tabControl->End(); ?>
</form>