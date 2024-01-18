<?

namespace Welpodron\Feedback\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Web\Json;
use Bitrix\Main\Error;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\Mail\Event as MailEvent;
use Bitrix\Main\Event as MainEvent;
use Bitrix\Main\UserConsent\Consent;
use Bitrix\Main\UserConsent\Agreement;
use Bitrix\Iblock\PropertyTable;

class Receiver extends Controller
{
    //??? TODO: v3 Добавить хуки перед заполнением HTML ответа пользователю, чтобы можно было изменить его содержимое
    //! TODO: v3 Переместить в основной класс модуля
    //! TODO: v3 Добавить работу с файлами
    const DEFAULT_FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
    const DEFAULT_FORM_GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
    const DEFAULT_MODULE_ID = 'welpodron.feedback';
    const DEFAULT_GOOGLE_URL = "https://www.google.com/recaptcha/api/siteverify";

    // v2 События
    const DEFAULT_EVENTS_BEFORE_GLOBAL_VALIDATION = 'OnBeforeGlobalValidation';
    const DEFAULT_EVENTS_AFTER_GLOBAL_VALIDATION = 'OnAfterGlobalValidation';
    const DEFAULT_EVENTS_BEFORE_FIELD_VALIDATION = 'OnBeforeFieldValidation';
    const DEFAULT_EVENTS_AFTER_FIELD_VALIDATION = 'OnAfterFieldValidation';

    const DEFAULT_ERROR_CONTENT = "При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта";


    //! Данный метод обязателен если мы не хотим получить invalid_authentication https://qna.habr.com/q/1043030
    protected function getDefaultPreFilters()
    {
        return [];
    }

    private function validateFile($arField, $arFile, $rawValue)
    {
        $maxFileSize = intval(Option::get(SELF::DEFAULT_MODULE_ID, 'MAX_FILE_SIZE')) * 1024 * 1024;

        if ($maxFileSize) {
            if ($arFile['size'] > $maxFileSize) {
                $error = 'Поле: "' . $arField['NAME'] . '"' . ' содержит файл: "' . $arFile['name'] . '" размером: ' . $arFile['size']  . ' байт, максимально допустимый размер файла: ' . $maxFileSize . ' байт';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                    'FIELD_ID' => $arField['ID'],
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $rawValue,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }
        }

        $supportedTypes = [];
        $supportedTypesRaw = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim(strval($arField['FILE_TYPE'])));
        if ($supportedTypesRaw) {
            $arSupportedTypesRawFiltered = array_filter($supportedTypesRaw, function ($value) {
                return $value !== null && $value !== '';
            });
            $supportedTypes = array_values($arSupportedTypesRawFiltered);
        }

        if ($supportedTypes) {
            $currentFileExt = GetFileExtension($arFile['name']);
            if (!in_array($currentFileExt, $supportedTypes)) {
                $error = 'Поле: "' . $arField['NAME'] . '" содержит файл: "' . $arFile['name'] . '" неподдерживаемого типа: "' . $currentFileExt . '"' . ' поддерживаемые типы: "' . implode(' ', $supportedTypes) . '"';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                    'FIELD_ID' => $arField['ID'],
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $rawValue,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }
        }

        return true;
    }

    private function validateField($arField, $_value, $bannedSymbols = [])
    {
        if (is_array($_value)) {
            $value = $_value;
        } else {
            $value = trim(strval($_value));
        }

        // Проверка на обязательность заполнения
        if ($arField['IS_REQUIRED'] == 'Y' && empty($value)) {
            $error = 'Поле: "' . $arField['NAME'] . '" является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
            return [
                'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                'FIELD_ID' => $arField['ID'],
                'FIELD_CODE' => $arField['CODE'],
                'FIELD_VALUE' => $value,
                'FIELD_VALID' => false,
                'FIELD_ERROR' => $error,
            ];
        }

        if ($arField['PROPERTY_TYPE'] === "F") {
            $maxFilesAmount = intval(Option::get(self::DEFAULT_MODULE_ID, 'MAX_FILES_AMOUNT'));

            if ($arField['MULTIPLE'] !== "Y") {
                $maxFilesAmount = 1;
            }

            if ($maxFilesAmount) {
                if (count($value) > $maxFilesAmount) {
                    $error = 'Поле: "' . $arField['NAME'] . '"' . ' содержит ' . count($value)  . ' файлов, максимально допустимое количество файлов: ' . $maxFilesAmount;
                    $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                    return [
                        'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                        'FIELD_ID' => $arField['ID'],
                        'FIELD_CODE' => $arField['CODE'],
                        'FIELD_VALUE' => $value,
                        'FIELD_VALID' => false,
                        'FIELD_ERROR' => $error,
                    ];
                }
            }

            $maxFilesSize = intval(Option::get(self::DEFAULT_MODULE_ID, 'MAX_FILES_SIZES')) * 1024 * 1024;

            $currentTotalSize = 0;

            foreach ($value as $file) {
                if (!$this->validateFile($arField, $file, $value)) {
                    return;
                }

                $currentTotalSize += $file['size'];

                if ($maxFilesSize) {
                    if ($currentTotalSize > $maxFilesSize) {
                        $error = 'Поле: "' . $arField['NAME'] . '"' . ' содержит файлы суммарным размером: ' . $currentTotalSize  . ' байт, максимально допустимый суммарный размер файлов: ' . $maxFilesSize . ' байт';
                        $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                        return [
                            'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                            'FIELD_ID' => $arField['ID'],
                            'FIELD_CODE' => $arField['CODE'],
                            'FIELD_VALUE' => $value,
                            'FIELD_VALID' => false,
                            'FIELD_ERROR' => $error,
                        ];
                    }
                }

                if ($arField['MULTIPLE'] !== "Y") {
                    break;
                }
            }
        } elseif ($arField['PROPERTY_TYPE'] === "N") {
            if (!is_numeric($value)) {
                $error =  'Поле: "' . $arField['NAME'] . '" должно быть числом';
                $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                return [
                    'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                    'FIELD_ID' => $arField['ID'],
                    'FIELD_CODE' => $arField['CODE'],
                    'FIELD_VALUE' => $value,
                    'FIELD_VALID' => false,
                    'FIELD_ERROR' => $error,
                ];
            }
        } else {
            // Проверка на наличие запрещенных символов 
            if (!is_array($value) && strlen($value)) {
                if ($bannedSymbols) {
                    foreach ($bannedSymbols as $bannedSymbol) {
                        if (strpos($value, $bannedSymbol) !== false) {
                            $error = 'Поле: "' . $arField['NAME'] . '" содержит один из запрещенных символов: "' . implode(' ', $bannedSymbols) . '"';
                            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arField['CODE']));
                            return [
                                'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
                                'FIELD_ID' => $arField['ID'],
                                'FIELD_CODE' => $arField['CODE'],
                                'FIELD_VALUE' => $value,
                                'FIELD_VALID' => false,
                                'FIELD_ERROR' => $error,
                            ];
                        }
                    }
                }
            }
        }

        return [
            'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
            'FIELD_ID' => $arField['ID'],
            'FIELD_CODE' => $arField['CODE'],
            'FIELD_VALUE' => $value,
            'FIELD_VALID' => true,
            'FIELD_ERROR' => '',
        ];
    }

    private function validateCaptcha($token)
    {
        if (!$token) {
            throw new \Exception('Ожидался токен от капчи. Запрос должен иметь заполненное POST поле: "g-recaptcha-response"');
        }

        $secretCaptchaKey = Option::get(self::DEFAULT_MODULE_ID, 'GOOGLE_CAPTCHA_SECRET_KEY');

        $httpClient = new HttpClient();
        $googleCaptchaResponse = Json::decode($httpClient->post(self::DEFAULT_GOOGLE_URL, ['secret' => $secretCaptchaKey, 'response' => $token], true));

        if (!$googleCaptchaResponse['success']) {
            throw new \Exception('Произошла ошибка при попытке обработать ответ от сервера капчи, проверьте задан ли параметр "GOOGLE_CAPTCHA_SECRET_KEY" в настройках модуля');
        }
    }

    private function validateAgreement($arDataRaw)
    {
        $agreementProp = Option::get(self::DEFAULT_MODULE_ID, 'AGREEMENT_PROPERTY');

        $agreementId = intval($arDataRaw[$agreementProp]);

        if ($agreementId <= 0) {
            $error = 'Поле является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $agreementProp));
            return;
        }

        $agreement = new Agreement($agreementId);

        if (!$agreement->isExist() || !$agreement->isActive()) {
            throw new \Exception('Соглашение c id ' . $agreementId . ' не найдено или не активно');
        }

        return true;
    }

    private function validateIblock($arDataRaw)
    {
        $iblockProp = trim(Option::get(self::DEFAULT_MODULE_ID, 'IBLOCK_PROPERTY'));

        if (!$iblockProp) {
            throw new \Exception('Не задан код поля инфоблока в настройках модуля');
        }

        $iblockId = intval($arDataRaw[$iblockProp]);

        if ($iblockId <= 0) {
            $error = 'Поле является обязательным для заполнения';
            $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $iblockProp));
            return;
        }

        if (!\CIBlock::GetList([], ['ID' => $iblockId])->Fetch()) {
            throw new \Exception('Инфоблок c id ' . $iblockId . ' не найден');
        }

        $arAllowedIblocks = explode(',', Option::get(self::DEFAULT_MODULE_ID, 'RESTRICTIONS_IBLOCK_ID'));

        if (!is_array($arAllowedIblocks) || empty($arAllowedIblocks)) {
            throw new \Exception('Не заданы разрешенные инфоблоки в настройках модуля');
        }

        if (!in_array($arDataRaw[$iblockProp], $arAllowedIblocks)) {
            throw new \Exception('Инфоблок c id ' . $iblockId . ' не разрешен для использования');
        }

        return true;
    }

    // Вызов из BX.ajax.runAction - welpodron:feedback.Receiver.add
    public function addAction()
    {
        global $APPLICATION;

        try {
            if (!$_SERVER['HTTP_USER_AGENT']) {
                throw new \Exception('Поисковые боты не могут отправлять формы');
            } elseif (preg_match('/bot|crawl|curl|dataprovider|search|get|spider|find|java|majesticsEO|google|yahoo|teoma|contaxe|yandex|libwww-perl|facebookexternalhit/i', $_SERVER['HTTP_USER_AGENT'])) {
                throw new \Exception('Поисковые боты не могут отправлять формы');
            }

            // В этой версии модуль использует инфоблок как основное хранилище данных
            if (!Loader::includeModule('iblock')) {
                throw new \Exception('Модуль инфоблоков не установлен');
            }

            $request = $this->getRequest();
            $arDataRaw = $request->getPostList()->toArray();

            // v2 события
            $event = new MainEvent(
                self::DEFAULT_MODULE_ID,
                self::DEFAULT_EVENTS_BEFORE_GLOBAL_VALIDATION,
                $arDataRaw
            );

            $event->send();

            $arDataRaw = $event->getParameters();

            // Проверка что данные отправлены используя сайт с которого была отправлена форма
            // Данные должны содержать идентификатор сессии битрикса 
            if ($arDataRaw['sessid'] !== bitrix_sessid()) {
                throw new \Exception('Неверный идентификатор сессии');
            }

            // Проверка капчи если она включена
            $useCaptcha = Option::get(self::DEFAULT_MODULE_ID, 'USE_CAPTCHA') == "Y";

            if ($useCaptcha) {
                $this->validateCaptcha($arDataRaw['g-recaptcha-response']);
            }

            // v2 пользовательское соглашение
            $useCheckAgreement = Option::get(self::DEFAULT_MODULE_ID, 'USE_AGREEMENT_CHECK') == "Y";

            if ($useCheckAgreement) {
                if (!$this->validateAgreement($arDataRaw)) {
                    return;
                }
            }

            $bannedSymbols = [];
            $bannedSymbolsRaw = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim(strval(Option::get(self::DEFAULT_MODULE_ID, 'BANNED_SYMBOLS'))));
            if ($bannedSymbolsRaw) {
                $bannedSymbolsRawFiltered = array_filter($bannedSymbolsRaw, function ($value) {
                    return $value !== null && $value !== '';
                });
                $bannedSymbols = array_values($bannedSymbolsRawFiltered);
            }

            // CIBlockProperty::GetList - получение списка свойств инфоблока до ORM
            // TODO: Придумать как получить список свойств инфоблока с сортировкой по обязательности заполнения используя ORM
            // Так как формы скорее всего будут выглядеть абсолютно одинаково, то скорее всего нет смысла делить на разные

            if (!$this->validateIblock($arDataRaw)) {
                return;
            }

            $iblockProp = trim(Option::get(self::DEFAULT_MODULE_ID, 'IBLOCK_PROPERTY'));

            if (!$iblockProp) {
                throw new \Exception('Не задан код поля инфоблока в настройках модуля');
            }

            $iblockId = intval($arDataRaw[$iblockProp]);

            $query = PropertyTable::query();
            $query->setSelect(['ID', 'IBLOCK_ID', 'CODE', 'NAME', 'PROPERTY_TYPE', 'IS_REQUIRED', 'MULTIPLE', 'FILE_TYPE']);
            $query->where('IBLOCK_ID', $iblockId);
            $query->where('ACTIVE', 'Y');
            $query->where('CODE', '!=', '');

            $arProps = $query->exec()->fetchAll();

            $arDataValid = [];

            // Сбор информации о пользователе (СЛУЖЕБНЫЕ ПОЛЯ)
            //! НЕ ОТПРАВЛЯЮТСЯ В СОБЫТИЯ !
            $arDataUser = [
                'USER_AGENT' => $request->getUserAgent(),
                'USER_ID' => CurrentUser::get()->getId(),
                'USER_IP' => $request->getRemoteAddress(),
                'PAGE' => Context::getCurrent()->getServer()->get('HTTP_REFERER'),
                'SESSION_ID' => bitrix_sessid(),
            ];

            /*

            OnBeforeFieldValidation - событие ПЕРЕД валидацией поля
            [
                'FIELD_IBLOCK_ID' - id инфоблока
                'FIELD_ID' - id поля
                'FIELD_CODE' - код поля
                'FIELD_VALUE' - текущее значение поля полученное из arDataRaw
                'FIELD_VALID' - является ли поле валидным (по умолчанию все поля НЕ ВАЛИДНЫ 
                так как валидация должна пройти хотя бы 1 раз для каждого поля) 
                (однако если обработчик события установит значение в true, то валидация не будет проходить и будет
                считаться что поле валидно и его проверять дальше не нужно ОДНАКО OnAfterFieldValidation будет все равно вызван)
                'FIELD_ERROR' - текст ошибки валидации поля
            ]

            OnAfterFieldValidation - событие ПОСЛЕ валидации поля (последний рубеж перед занесением в валидные поля)
            [
                'FIELD_IBLOCK_ID' - id инфоблока
                'FIELD_ID' - id поля
                'FIELD_CODE' - код поля
                'FIELD_VALUE' - текущее значение поля полученное из arDataRaw
                'FIELD_VALID' - является ли поле валидным после валидации (в случае если обработчик события
                 установит значение в true, когда поле было не валидно false, то поле будет считаться валидным и 
                 будет добавлено в валидные поля)
                )
                'FIELD_ERROR' - текст ошибки валидации поля
            ]

            */

            foreach ($arProps as $arProp) {
                // Поддержка только полей имеющий символьный код
                if ($arProp['CODE']) {
                    // Пропускаем служебные поля 
                    if (in_array(strtoupper($arProp['CODE']), array_keys($arDataUser))) {
                        continue;
                    }

                    // v2 события 
                    $event = new MainEvent(
                        self::DEFAULT_MODULE_ID,
                        self::DEFAULT_EVENTS_BEFORE_FIELD_VALIDATION,
                        [
                            'FIELD_IBLOCK_ID' => $iblockId,
                            'FIELD_ID' => $arProp['ID'],
                            'FIELD_CODE' => $arProp['CODE'],
                            'FIELD_VALUE' => $arDataRaw[$arProp['CODE']],
                            'FIELD_VALID' => false,
                            'FIELD_ERROR' => '',
                        ]
                    );

                    $event->send();

                    $arResult = $event->getParameters();

                    if ($arResult['FIELD_VALID']) {
                    } else {
                        if ($arProp['PROPERTY_TYPE'] === "F") {
                            $postRawValue = $request->getFile($arProp['CODE']);

                            $postValue = [];

                            if (is_array($postRawValue)) {
                                foreach ($postRawValue['size'] as $key => $size) {
                                    if ($size <= 0) {
                                        continue;
                                    }

                                    $postValue[] = [
                                        'name' => $postRawValue['name'][$key],
                                        'type' => $postRawValue['type'][$key],
                                        'tmp_name' => $postRawValue['tmp_name'][$key],
                                        'error' => $postRawValue['error'][$key],
                                        'size' => $postRawValue['size'][$key],
                                    ];
                                }
                            }

                            $arResult = $this->validateField($arProp, $postValue, $bannedSymbols);
                        } else {
                            $arResult = $this->validateField($arProp, $arDataRaw[$arProp['CODE']], $bannedSymbols);
                        }
                    }

                    // v2 события
                    $event = new MainEvent(
                        self::DEFAULT_MODULE_ID,
                        self::DEFAULT_EVENTS_AFTER_FIELD_VALIDATION,
                        $arResult
                    );

                    $event->send();

                    $arResult = $event->getParameters();

                    if ($arResult['FIELD_VALID']) {
                        if ($arProp['PROPERTY_TYPE'] === "F") {
                            $arDataValid[$arProp['CODE']] = $postValue;
                        } else {
                            $arDataValid[$arProp['CODE']] = $arDataRaw[$arProp['CODE']];
                        }
                    } else {
                        return;
                    }
                }
            }

            // v2 события
            $event = new MainEvent(
                self::DEFAULT_MODULE_ID,
                self::DEFAULT_EVENTS_AFTER_GLOBAL_VALIDATION,
                $arDataValid
            );

            $event->send();

            $arDataValid = $event->getParameters();

            $arDataMerged = array_merge($arDataValid, $arDataUser);

            // ! Хук для сохранения данных не нужен так как тут идет работа с инфоблоками и по сути это уже нативный хук
            // Сохранение данных в инфоблок
            $useSave = Option::get(self::DEFAULT_MODULE_ID, 'USE_SAVE') == "Y";

            if ($useSave) {
                $arFields = [
                    'IBLOCK_ID' => $iblockId,
                    'NAME' => 'Заявка ' . (new DateTime())->format("d.m.Y H:i:s"),
                    'PROPERTY_VALUES' => $arDataMerged
                ];

                $dbEl = new \CIBlockElement;

                $dbElResult = $dbEl->Add($arFields);

                if (!$dbElResult) {
                    throw new \Exception($dbEl->LAST_ERROR);
                }
            }

            // v2 Добавление в список согласий
            if ($useCheckAgreement) {
                $agreementId = null;

                $agreementProp = Option::get(self::DEFAULT_MODULE_ID, 'AGREEMENT_PROPERTY');

                if (isset($arDataValid[$agreementProp])) {
                    $agreementId = intval($arDataValid[$agreementProp]);
                } else {
                    $agreementId = intval($arDataRaw[$agreementProp]);
                }

                if ($agreementId > 0) {
                    Consent::addByContext($agreementId, null, null, [
                        'URL' => Context::getCurrent()->getServer()->get('HTTP_REFERER'),
                    ]);
                }
            }

            // Нотификация администратора
            $useNotify = Option::get(self::DEFAULT_MODULE_ID, 'USE_NOTIFY') == "Y";

            if ($useNotify) {
                $notifyEvent = Option::get(self::DEFAULT_MODULE_ID, 'NOTIFY_TYPE');
                $notifyEmail = Option::get(self::DEFAULT_MODULE_ID, 'NOTIFY_EMAIL');
                $notifyResult = MailEvent::send([
                    'EVENT_NAME' => $notifyEvent,
                    'LID' => Context::getCurrent()->getSite(),
                    'C_FIELDS' => array_merge($arDataMerged, ['EMAIL_TO' => $notifyEmail]),
                ]);

                if (!$notifyResult->isSuccess()) {
                    throw new \Exception(implode(", ", $notifyResult->getErrorMessages()));
                }
            }

            // v2 Уведомление пользователю
            $useReturnNotify = Option::get(self::DEFAULT_MODULE_ID, 'USE_RETURN_NOTIFY') == "Y";

            if ($useReturnNotify) {
                $notifyEvent = Option::get(self::DEFAULT_MODULE_ID, 'RETURN_NOTIFY_TYPE');

                $notifyProp = Option::get(self::DEFAULT_MODULE_ID, 'RETURN_NOTIFY_PROPERTY');

                $notifyEmail = null;

                if (isset($arDataValid[$notifyProp])) {
                    $notifyEmail = $arDataValid[$notifyProp];
                } else {
                    $notifyEmail = $arDataRaw[$notifyProp];
                }

                if ($notifyEmail) {
                    $notifyResult = MailEvent::send([
                        'EVENT_NAME' => $notifyEvent,
                        'LID' => Context::getCurrent()->getSite(),
                        'C_FIELDS' => array_merge($arDataValid, [$notifyProp => $notifyEmail]),
                    ]);

                    if (!$notifyResult->isSuccess()) {
                        throw new \Exception(implode(", ", $notifyResult->getErrorMessages()));
                    }
                }
            }

            $useSuccessContent = Option::get(self::DEFAULT_MODULE_ID, 'USE_SUCCESS_CONTENT');

            $templateIncludeResult = "";

            if ($useSuccessContent == 'Y') {
                $templateIncludeResult =  Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_CONTENT_DEFAULT');

                $successFile = Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_FILE');

                if ($successFile) {
                    ob_start();
                    $APPLICATION->IncludeFile($successFile, [
                        'arMutation' => [
                            'PATH' => $successFile,
                            'PARAMS' => $arDataMerged,
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                }
            }

            return $templateIncludeResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode()));
                return;
            }

            try {
                $useErrorContent = Option::get(self::DEFAULT_MODULE_ID, 'USE_ERROR_CONTENT');

                if ($useErrorContent == 'Y') {
                    $errorFile = Option::get(self::DEFAULT_MODULE_ID, 'ERROR_FILE');

                    if (!$errorFile) {
                        $this->addError(new Error(Option::get(self::DEFAULT_MODULE_ID, 'ERROR_CONTENT_DEFAULT'), self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                        return;
                    }

                    ob_start();
                    $APPLICATION->IncludeFile($errorFile, [
                        'arMutation' => [
                            'PATH' => $errorFile,
                            'PARAMS' => [],
                        ]
                    ], ["SHOW_BORDER" => false, "MODE" => "php"]);
                    $templateIncludeResult = ob_get_contents();
                    ob_end_clean();
                    $this->addError(new Error($templateIncludeResult));
                    return;
                }

                $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode()));
                    return;
                } else {
                    $this->addError(new Error(self::DEFAULT_ERROR_CONTENT, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                    return;
                }
            }
        }
    }
}
