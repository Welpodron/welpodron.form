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

class Receiver extends Controller
{
    //??? TODO: v3 Добавить хуки перед заполнением HTML ответа пользователю, чтобы можно было изменить его содержимое
    //??? TODO: v3 Добавить возможность пробрасывать переменные в PHP шаблон ответа пользователю (например ошибки, результаты валидации и т.д.)
    //! TODO: v2.1 Добавить поддержку обработки поля пользовательского соглашения в настройках задавать свойство + проверка на обязательность + добавление в таблицу принятых пользовательских соглашений Bitrix API
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


    //! Данный метод обязателен если мы не хотим получить invalid_authentication https://qna.habr.com/q/1043030
    protected function getDefaultPreFilters()
    {
        return [];
    }

    private function validateField($arField, $value, $bannedSymbols = [])
    {
        // Проверка на обязательность заполнения
        if ($arField['IS_REQUIRED'] == 'Y' && !strlen($value)) {
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

        // Проверка на наличие запрещенных символов 
        if (strlen($value)) {
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

        return [
            'FIELD_IBLOCK_ID' => $arField['IBLOCK_ID'],
            'FIELD_ID' => $arField['ID'],
            'FIELD_CODE' => $arField['CODE'],
            'FIELD_VALUE' => $value,
            'FIELD_VALID' => true,
            'FIELD_ERROR' => '',
        ];
    }

    // Вызов из BX.ajax.runAction - welpodron:feedback.Receiver.add
    public function addAction()
    {
        global $APPLICATION;

        try {
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
                $captchaToken = $arDataRaw['g-recaptcha-response'];

                if (!$captchaToken) {
                    throw new \Exception('Ожидался токен от капчи. Запрос должен иметь заполненное POST поле: "g-recaptcha-response"');
                }

                $secretCaptchaKey = Option::get(self::DEFAULT_MODULE_ID, 'GOOGLE_CAPTCHA_SECRET_KEY');

                $httpClient = new HttpClient();
                $googleCaptchaResponse = Json::decode($httpClient->post(self::DEFAULT_GOOGLE_URL, ['secret' => $secretCaptchaKey, 'response' => $captchaToken], true));

                if (!$googleCaptchaResponse['success']) {
                    throw new \Exception('Произошла ошибка при попытке обработать ответ от сервера капчи, проверьте задан ли параметр "GOOGLE_CAPTCHA_SECRET_KEY" в настройках модуля');
                }
            }

            // v2 пользовательское соглашение
            $useCheckAgreement = Option::get(self::DEFAULT_MODULE_ID, 'USE_AGREEMENT_CHECK') == "Y";

            if ($useCheckAgreement) {
                $agreementCheckProp = Option::get(self::DEFAULT_MODULE_ID, 'AGREEMENT_CHECK_PROPERTY');

                if (!$agreementCheckProp) {
                    throw new \Exception('Ожидалось что в настройках модуля будет задано НЕ ПУСТОЕ свойство для проверки пользовательского соглашения');
                }

                $agreementCheck = $arDataRaw[$agreementCheckProp];

                if ($agreementCheck != true) {
                    $error = 'Поле является обязательным для заполнения';
                    $this->addError(new Error($error, self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $agreementCheckProp));
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

            $iblock = Option::get(self::DEFAULT_MODULE_ID, 'IBLOCK_ID');

            $dbProps = \CIBlockProperty::GetList(
                [],
                ['IBLOCK_ID' => $iblock],
            );

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

            while ($arProp = $dbProps->Fetch()) {
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
                            'FIELD_IBLOCK_ID' => $iblock,
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
                        $arResult = $this->validateField($arProp, $arDataRaw[$arProp['CODE']], $bannedSymbols);
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
                        $arDataValid[$arProp['CODE']] = $arDataRaw[$arProp['CODE']];
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
                    'IBLOCK_ID' => $iblock,
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

                $agreementIdProp = Option::get(self::DEFAULT_MODULE_ID, 'AGREEMENT_ID_PROPERTY');

                if (isset($arDataValid[$agreementIdProp])) {
                    $agreementId = $arDataValid[$agreementIdProp];
                } else {
                    $agreementId = $arDataRaw[$agreementIdProp];
                }

                if ($agreementId) {
                    Consent::addByContext($agreementId, null, null, [
                        'URL' => $arDataUser['PAGE'],
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

            $successFile = Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_FILE');

            if (!$successFile) {
                return Option::get(self::DEFAULT_MODULE_ID, 'SUCCESS_CONTENT_DEFAULT');
            }

            ob_start();
            $APPLICATION->IncludeFile($successFile, [], ["SHOW_BORDER" => false, "MODE" => "php"]);
            $templateIncludeResult = ob_get_contents();
            ob_end_clean();

            return $templateIncludeResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode()));
                return;
            }

            try {
                $errorFile = Option::get(self::DEFAULT_MODULE_ID, 'ERROR_FILE');

                if (!$errorFile) {
                    $this->addError(new Error(Option::get(self::DEFAULT_MODULE_ID, 'ERROR_CONTENT_DEFAULT'), self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                    return;
                }

                ob_start();
                $APPLICATION->IncludeFile($errorFile, [], ["SHOW_BORDER" => false, "MODE" => "php"]);
                $templateIncludeResult = ob_get_contents();
                ob_end_clean();
                $this->addError(new Error($templateIncludeResult, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                return;
            } catch (\Throwable $th) {
                if (CurrentUser::get()->isAdmin()) {
                    $this->addError(new Error($th->getMessage(), $th->getCode()));
                    return;
                } else {
                    $this->addError(new Error(Option::get(self::DEFAULT_MODULE_ID, 'ERROR_CONTENT_DEFAULT'), self::DEFAULT_FORM_GENERAL_ERROR_CODE));
                    return;
                }
            }
        }
    }
}
