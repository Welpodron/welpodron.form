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
use Bitrix\Main\Mail\Event;

class Receiver extends Controller
{
    const DEFAULT_FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";
    const DEFAULT_FORM_GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
    const DEFAULT_MODULE_ID = 'welpodron.feedback';
    const DEFAULT_GOOGLE_URL = "https://www.google.com/recaptcha/api/siteverify";

    //! Данный метод обязателен если мы не хотим получить invalid_authentication https://qna.habr.com/q/1043030
    protected function getDefaultPreFilters()
    {
        return [];
    }

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

            // Сбор информации о пользователе
            $arDataUser = [
                'USER_AGENT' => $request->getUserAgent(),
                'USER_ID' => CurrentUser::get()->getId(),
                'USER_IP' => $request->getRemoteAddress(),
                'PAGE' => Context::getCurrent()->getServer()->get('HTTP_REFERER'),
                'SESSION_ID' => bitrix_sessid(),
            ];

            while ($arProp = $dbProps->Fetch()) {
                // Поддержка только полей имеющий символьный код
                if ($arProp['CODE']) {
                    // Пропускаем служебные поля 
                    if (in_array(strtoupper($arProp['CODE']), array_keys($arDataUser))) {
                        continue;
                    }

                    // Проверка на обязательность заполнения
                    if ($arProp['IS_REQUIRED'] == 'Y' && !strlen($arDataRaw[$arProp['CODE']])) {
                        $this->addError(new Error('Поле: "' . $arProp['NAME'] . '" является обязательным для заполнения', self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arProp['CODE']));
                        return;
                    }

                    // Проверка на наличие запрещенных символов 
                    if (strlen($arDataRaw[$arProp['CODE']])) {
                        if ($bannedSymbols) {
                            foreach ($bannedSymbols as $bannedSymbol) {
                                if (strpos($arDataRaw[$arProp['CODE']], $bannedSymbol) !== false) {
                                    $this->addError(new Error('Поле: "' . $arProp['NAME'] . '" содержит один из запрещенных символов: "' . implode(' ', $bannedSymbols) . '"', self::DEFAULT_FIELD_VALIDATION_ERROR_CODE, $arProp['CODE']));
                                    return;
                                }
                            }
                        }
                    }

                    $arDataValid[$arProp['CODE']] = $arDataRaw[$arProp['CODE']];
                }
            }

            $arDataMerged = array_merge($arDataValid, $arDataUser);

            // Сохранение данных в инфоблок
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

            // Нотификация администратора
            $useNotify = Option::get(self::DEFAULT_MODULE_ID, 'USE_NOTIFY') == "Y";

            if ($useNotify) {
                $notifyEvent = Option::get(self::DEFAULT_MODULE_ID, 'NOTIFY_TYPE');
                $notifyEmail = Option::get(self::DEFAULT_MODULE_ID, 'NOTIFY_EMAIL');
                $notifyResult = Event::send([
                    'EVENT_NAME' => $notifyEvent,
                    'LID' => Context::getCurrent()->getSite(),
                    'C_FIELDS' => array_merge($arDataMerged, ['EMAIL_TO' => $notifyEmail]),
                ]);

                if (!$notifyResult->isSuccess()) {
                    throw new \Exception(implode(", ", $notifyResult->getErrorMessages()));
                }
            }

            ob_start();
            $APPLICATION->IncludeFile("/local/modules/welpodron.feedback/templates/add_success.php", [], ["SHOW_BORDER" => false, "MODE" => "php"]);
            $templateIncludeResult = ob_get_contents();
            ob_end_clean();

            return $templateIncludeResult;
        } catch (\Throwable $th) {
            if (CurrentUser::get()->isAdmin()) {
                $this->addError(new Error($th->getMessage(), $th->getCode()));
                return;
            }

            ob_start();
            $APPLICATION->IncludeFile("/local/modules/welpodron.feedback/templates/add_error.php", [], ["SHOW_BORDER" => false, "MODE" => "php"]);
            $templateIncludeResult = ob_get_contents();
            ob_end_clean();
            $this->addError(new Error($templateIncludeResult, self::DEFAULT_FORM_GENERAL_ERROR_CODE));
            return;
        }
    }
}
