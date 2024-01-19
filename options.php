<?
if (!defined('B_PROLOG_INCLUDED') || constant('B_PROLOG_INCLUDED') !== true) {
    die();
}

use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

use Welpodron\Core\Helper;

Loader::includeModule("iblock");

$moduleId = 'welpodron.form';

$dbIblocks = CIBlock::GetList();

$arIblocks['-1'] = 'Выберите разрешенные инфоблок(и)';

while ($arIblock = $dbIblocks->Fetch()) {
    $arIblocks[$arIblock['ID']] = '[' . $arIblock['ID'] . '] ' . $arIblock["NAME"];
}

// v2 убрано только 1 определенное почтовое событие, теперь можно выбрать любое
$dbMailEvents = CEventType::GetList();
$arMailEvents['-1'] = 'Выберите почтовое событие';

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
                'TITLE' => 'Основные настройки',
                'OPTIONS' => [
                    [
                        'NAME' => 'IBLOCK_PROPERTY',
                        'LABEL' => 'Код поля в котором хранится инфоблок',
                        'VALUE' => Option::get($moduleId, 'IBLOCK_PROPERTY'),
                        'TYPE' => 'text',
                        'DESCRIPTION' => 'Код поля в котором хранится инфоблок не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента',
                        'REQUIRED' => 'Y',
                    ],
                    [
                        'NAME' => 'RESTRICTIONS_IBLOCK_ID',
                        'LABEL' => 'Разрешенные инфоблок(и)',
                        'VALUE' => Option::get($moduleId, 'RESTRICTIONS_IBLOCK_ID'),
                        'TYPE' => 'selectbox',
                        'MULTIPLE' => 'Y',
                        'REQUIRED' => 'Y',
                        'OPTIONS' => $arIblocks,
                    ],
                    [
                        'NAME' => 'USE_SAVE',
                        'LABEL' => 'Сохранять данные в инфоблок',
                        'VALUE' => Option::get($moduleId, 'USE_SAVE'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'USE_SAVE_NOTE',
                        'LABEL' => '<b style="color: red;">Внимание!</b> Убедитесь что выбранные выше инфоблоки имеют права на запись',
                        'TYPE' => 'note',
                        'RELATION' => 'USE_SAVE',
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
                    [
                        'LABEL' => 'Ограничения на файлы работают только со свойствами инфоблока типа "Файл"',
                        'TYPE' => 'note',
                    ],
                    [
                        'LABEL' => 'Значения файловых ограничений имеющие: 0, пустые, отрицательные и любые текстовые значения - без ограничений',
                        'TYPE' => 'note',
                    ],
                    [
                        'NAME' => 'MAX_FILE_SIZE',
                        'LABEL' => 'Максимальный размер загружаемого файла в МБ',
                        'VALUE' => Option::get($moduleId, 'MAX_FILE_SIZE'),
                        'TYPE' => 'number',
                        'MIN' => 0,
                    ],
                    [
                        'NAME' => 'MAX_FILES_SIZES',
                        'LABEL' => 'Максимальный суммарный размер загружаемых файлов в МБ (если свойство поддерживает множественное значение)',
                        'VALUE' => Option::get($moduleId, 'MAX_FILES_SIZES'),
                        'TYPE' => 'number',
                        'MIN' => 0,
                    ],
                    [
                        'NAME' => 'MAX_FILES_AMOUNT',
                        'LABEL' => 'Максимальное количество загружаемых файлов (если свойство поддерживает множественное значение)',
                        'VALUE' => Option::get($moduleId, 'MAX_FILES_AMOUNT'),
                        'TYPE' => 'number',
                        'MIN' => 0,
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
                        'NAME' => 'AGREEMENT_PROPERTY',
                        'LABEL' => 'Код поля в котором хранится согласие на обработку персональных данных',
                        'DESCRIPTION' => 'Код поля в котором хранится согласие на обработку персональных данных не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента',
                        'VALUE' => Option::get($moduleId, 'AGREEMENT_PROPERTY'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_AGREEMENT_CHECK',
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
                        'RELATION' => 'USE_NOTIFY',
                    ],
                    [
                        'NAME' => 'NOTIFY_EMAIL',
                        'LABEL' => 'Email менеджера сайта',
                        'VALUE' => Option::get($moduleId, 'NOTIFY_EMAIL'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_NOTIFY',
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
                        'RELATION'  => 'USE_RETURN_NOTIFY',
                    ],
                    [
                        'NAME' => 'RETURN_NOTIFY_PROPERTY',
                        'LABEL' => 'Код поля в котором хранится email пользователя',
                        'VALUE' => Option::get($moduleId, 'RETURN_NOTIFY_PROPERTY'),
                        'TYPE' => 'text',
                        'RELATION'  => 'USE_RETURN_NOTIFY',
                        'DESCRIPTION' => 'Код поля в котором хранится email пользователя не обязательно должен присутствовать в инфоблоке и берется из данных, полученных с клиента'
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
                        'RELATION' => 'USE_CAPTCHA',
                    ],
                    [
                        'NAME' => 'GOOGLE_CAPTCHA_PUBLIC_KEY',
                        'LABEL' => 'Публичный ключ (ключ сайта)',
                        'VALUE' => Option::get($moduleId, 'GOOGLE_CAPTCHA_PUBLIC_KEY'),
                        'TYPE' => 'text',
                        'RELATION' => 'USE_CAPTCHA',
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
                        'NAME' => 'USE_SUCCESS_CONTENT',
                        'LABEL' => 'Использовать успешное сообщение',
                        'VALUE' => Option::get($moduleId, 'USE_SUCCESS_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'SUCCESS_FILE',
                        'LABEL' => 'PHP файл-шаблон успешного ответа',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_FILE'),
                        'TYPE' => 'file',
                        'DESCRIPTION' => 'Если PHP файл-шаблон успешного ответа не задан, то будет использоваться содержимое успешного ответа по умолчанию',
                        'RELATION'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'SUCCESS_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое успешного ответа по умолчанию',
                        'VALUE' => Option::get($moduleId, 'SUCCESS_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'RELATION'  => 'USE_SUCCESS_CONTENT',
                    ],
                    [
                        'NAME' => 'USE_ERROR_CONTENT',
                        'LABEL' => 'Использовать сообщение об ошибке',
                        'VALUE' => Option::get($moduleId, 'USE_ERROR_CONTENT'),
                        'TYPE' => 'checkbox',
                    ],
                    [
                        'NAME' => 'ERROR_FILE',
                        'LABEL' => 'PHP файл-шаблон ответа с ошибкой',
                        'VALUE' => Option::get($moduleId, 'ERROR_FILE'),
                        'TYPE' => 'file',
                        'DESCRIPTION' => 'Если PHP файл-шаблон ответа с ошибкой не задан, то будет использоваться содержимое ответа с ошибкой по умолчанию',
                        'RELATION'  => 'USE_ERROR_CONTENT',
                    ],
                    [
                        'NAME' => 'ERROR_CONTENT_DEFAULT',
                        'LABEL' => 'Содержимое ответа с ошибкой по умолчанию',
                        'VALUE' => Option::get($moduleId, 'ERROR_CONTENT_DEFAULT'),
                        'TYPE' => 'editor',
                        'RELATION'  => 'USE_ERROR_CONTENT',
                    ],
                ],
            ]
        ]
    ],
];

if (Loader::includeModule('welpodron.core')) {
    Helper::buildOptions($moduleId, $arTabs);
} else {
    echo 'Модуль welpodron.core не установлен';
}
