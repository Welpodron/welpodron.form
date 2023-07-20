<?

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;

class welpodron_feedback extends CModule
{
    const DEFAULT_IBLOCK_TYPE = "welpodron_feedback";
    const DEFAULT_EVENT_TYPE = 'WELPODRON_FEEDBACK';
    const DEFAULT_RETURN_EVENT_TYPE = 'WELPODRON_FEEDBACK_RETURN';

    public function InstallFiles()
    {
        global $APPLICATION;

        try {
            // На данный момент папка перемещается в local пространство
            if (!CopyDirFiles(__DIR__ . '/components/', Application::getDocumentRoot() . '/local/components', true, true)) {
                $APPLICATION->ThrowException('Не удалось скопировать компоненты');
                return false;
            };
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }

    public function UnInstallFiles()
    {
        Directory::deleteDirectory(Application::getDocumentRoot() . '/local/components/welpodron/feedback.agreement');
        Directory::deleteDirectory(Application::getDocumentRoot() . '/local/components/welpodron/feedback.agreement.request');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            $APPLICATION->ThrowException('Версия главного модуля ниже 14.00.00');
            return false;
        }

        if (!$this->InstallFiles()) {
            return false;
        }

        if (!$this->InstallDb()) {
            return false;
        }

        if (!$this->InstallEvents()) {
            return false;
        }

        if (!$this->InstallOptions()) {
            return false;
        }

        ModuleManager::registerModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile('Установка модуля ' . $this->MODULE_ID, __DIR__ . '/step.php');
    }

    public function DoUninstall()
    {
        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();

        if ($request->get("step") < 2) {
            $APPLICATION->IncludeAdminFile('Деинсталляция модуля ' . $this->MODULE_ID, __DIR__ . '/unstep1.php');
        } elseif ($request->get("step") == 2) {
            $this->UnInstallFiles();
            $this->UnInstallEvents();
            $this->UnInstallOptions();
            // По умолчанию БД не удаляется 

            if ($request->get("savedata") != "Y")
                $this->UnInstallDB();

            ModuleManager::unRegisterModule($this->MODULE_ID);
            $APPLICATION->IncludeAdminFile('Деинсталляция модуля ' . $this->MODULE_ID, __DIR__ . '/unstep2.php');
        }
    }

    public function InstallOptions()
    {
        global $APPLICATION;

        try {
            foreach ($this->DEFAULT_OPTIONS as $optionName => $optionValue) {
                Option::set($this->MODULE_ID, $optionName, $optionValue);
            }
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }
        return true;
    }

    public function UnInstallOptions()
    {
        global $APPLICATION;

        try {
            foreach ($this->DEFAULT_OPTIONS as $optionName => $optionValue) {
                Option::delete($this->MODULE_ID, ['name' => $optionName]);
            }
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }
        return true;
    }

    public function InstallDb()
    {
        global $APPLICATION, $DB;

        try {
            if (!Loader::includeModule("iblock")) {
                $APPLICATION->ThrowException('Не удалось подключить модуль iblock');
                return false;
            };

            // Попытаться найти тип 
            $iblockType = CIBlockType::GetList([], ['=ID' => self::DEFAULT_IBLOCK_TYPE])->Fetch();

            if (!$iblockType) {
                $iblockType = new CIBlockType;

                $arFields = [
                    'ID' => self::DEFAULT_IBLOCK_TYPE,
                    'SECTIONS' => 'N',
                    'LANG' => [
                        'en' => [
                            'NAME' => 'Welpodron feedback',
                            'ELEMENT_NAME' => 'Feedback',
                        ],
                        'ru' => [
                            'NAME' => 'Welpodron заявки',
                            'ELEMENT_NAME' => 'Заявки'
                        ],
                    ]
                ];

                $DB->StartTransaction();

                $addResult = $iblockType->Add($arFields);

                if (!$addResult) {
                    $DB->Rollback();

                    $APPLICATION->ThrowException('Произошла ошибка при создании типа инфоблока' . $iblockType->LAST_ERROR);

                    return false;
                } else {
                    $DB->Commit();
                }
            }

            $dbSites = CSite::GetList($by = "sort", $order = "desc");
            while ($arSite = $dbSites->Fetch()) {
                $arSites[] = $arSite["LID"];
            }

            // Попытаться найти хотя бы один инфоблок
            $iblockId = null;
            $firstFoundIblock = CIBlock::GetList([], ['TYPE' => self::DEFAULT_IBLOCK_TYPE])->Fetch();

            if (!$firstFoundIblock) {
                $firstIblock = new CIBlock;

                $arFields = [
                    "NAME" => 'Welpodron заявки',
                    "IBLOCK_TYPE_ID" => self::DEFAULT_IBLOCK_TYPE,
                    "ELEMENTS_NAME" => "Заявки",
                    "ELEMENT_NAME" => "Заявка",
                    "ELEMENT_ADD" => "Добавить заявку",
                    "ELEMENT_EDIT" => "Изменить заявку",
                    "ELEMENT_DELETE" => "Удалить заявку",
                    "SITE_ID" => $arSites,
                ];

                $DB->StartTransaction();

                $iblockId = $firstIblock->Add($arFields);

                if (!$iblockId) {
                    $DB->Rollback();

                    $APPLICATION->ThrowException('Произошла ошибка при создании инфоблока' . $firstIblock->LAST_ERROR);

                    return false;
                } else {
                    $DB->Commit();
                }

                // TODO: 2 - Группа всех пользователей можно получать динамически ?
                CIBlock::SetPermission($iblockId, ["2" => "R"]);

                $arProps = [
                    [
                        "NAME" => "Имя",
                        "CODE" => "firstName",
                        "IS_REQUIRED" => "Y",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Телефон",
                        "CODE" => "tel",
                        "IS_REQUIRED" => "Y",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Email",
                        "CODE" => "email",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Комментарий",
                        "CODE" => "comment",
                        "USER_TYPE" => "",
                        "ROW_COUNT" => 3,
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Пользователь",
                        "CODE" => "USER_ID",
                        "USER_TYPE" => "UserID",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Сессия",
                        "CODE" => "SESSION_ID",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "IP",
                        "CODE" => "USER_IP",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "UserAgent",
                        "CODE" => "USER_AGENT",
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Страница отправки",
                        "CODE" => "PAGE",
                        "IBLOCK_ID" => $iblockId
                    ],
                ];

                foreach ($arProps as $prop) {
                    $iblockProp = new CIBlockProperty;

                    $DB->StartTransaction();

                    $iblockPropId = $iblockProp->Add($prop);

                    if (!$iblockPropId) {
                        $DB->Rollback();

                        $APPLICATION->ThrowException('Произошла ошибка при создании свойств инфоблока' . $iblockProp->LAST_ERROR);

                        return false;
                    } else {
                        $DB->Commit();
                    }
                }
            } else {
                $iblockId = $firstFoundIblock['ID'];
            }

            $this->DEFAULT_OPTIONS['IBLOCK_ID'] = $iblockId;
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }

    public function UnInstallDB()
    {
        Loader::includeModule("iblock");
        // Удалить iblock_type
        CIBlockType::Delete(self::DEFAULT_IBLOCK_TYPE);
    }

    public function InstallEvents()
    {
        global $APPLICATION, $DB;

        try {
            $dbSites = CSite::GetList($by = "sort", $order = "desc");
            while ($arSite = $dbSites->Fetch()) {
                $arSites[] = $arSite["LID"];
            }

            foreach ($arSites as $siteId) {
                $dbEt = CEventType::GetByID(self::DEFAULT_EVENT_TYPE, $siteId);
                $arEt = $dbEt->Fetch();

                if (!$arEt) {
                    $et = new CEventType;

                    $DB->StartTransaction();

                    $et = $et->Add([
                        'LID' => $siteId,
                        'EVENT_NAME' => self::DEFAULT_EVENT_TYPE,
                        'NAME' => 'Добавление заявки',
                        'EVENT_TYPE' => 'email',
                        'DESCRIPTION'  => '
                        #USER_ID# - ID Пользователя
                        #SESSION_ID# - Сессия пользователя
                        #USER_IP# - IP Адрес пользователя
                        #PAGE# - Страница отправки
                        #USER_AGENT# - UserAgent
                        #firstName# - Имя автора заявки
                        #tel# - Телефон автора заявки
                        #email# - Email автора заявки
                        #comment# - Комментарий автора заявки
                        #EMAIL_TO# - Email получателя письма
                        '
                    ]);

                    if (!$et) {
                        $DB->Rollback();

                        $APPLICATION->ThrowException('Не удалось создать почтовое событие' . $APPLICATION->LAST_ERROR);

                        return false;
                    } else {
                        $DB->Commit();
                    }
                }

                $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_EVENT_TYPE]);
                $arMess = $dbMess->Fetch();

                if (!$arMess) {
                    $mess = new CEventMessage;

                    $DB->StartTransaction();

                    $messId = $mess->Add([
                        'ACTIVE' => 'Y',
                        'EVENT_NAME' => self::DEFAULT_EVENT_TYPE,
                        'LID' => $siteId,
                        'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                        'EMAIL_TO' => '#EMAIL_TO#',
                        'SUBJECT' => '#SITE_NAME#: Добавлена заявка',
                        'BODY_TYPE' => 'html',
                        'MESSAGE' => '
                        <!DOCTYPE html>
                        <html lang="ru">
                        <head>
                        <meta charset="utf-8">
                        <title>Новая заявка</title>
                        </head>
                        <body>
                        <p>
                        На сайте была оформлена заявка
                        </p>
                        <p>
                        Имя автора заявки:
                        </p>
                        <p>
                        #firstName#
                        </p>
                        <p>
                        Телефон автора заявки:
                        </p>
                        <p>
                        #tel#
                        </p>
                        <p>
                        Почта автора заявки:
                        </p>
                        <p>
                        #email#
                        </p>
                        <p>
                        Комментарий автора заявки:
                        </p>
                        <p>
                        #comment#
                        </p>
                        <p>
                        Отправлено пользователем: #USER_ID#
                        </p>
                        <p>
                        Сессия пользователя: #SESSION_ID#
                        </p>
                        <p>
                        IP адрес отправителя: #USER_IP#
                        </p>
                        <p>
                        Страница отправки: <a href="#PAGE#">#PAGE#</a>
                        </p>
                        <p>
                        Используемый USER AGENT: #USER_AGENT#
                        </p>
                        <p>
                        Письмо сформировано автоматически.
                        </p>
                        </body>
                        </html>
                        '
                    ]);

                    if (!$messId) {
                        $DB->Rollback();

                        $APPLICATION->ThrowException('Произошла ошибка при создании почтового события' . $mess->LAST_ERROR);

                        return false;
                    } else {
                        $DB->Commit();
                    }
                }
            }

            $this->DEFAULT_OPTIONS['USE_NOTIFY'] = "Y";
            $this->DEFAULT_OPTIONS['NOTIFY_TYPE'] = self::DEFAULT_EVENT_TYPE;
            $this->DEFAULT_OPTIONS['NOTIFY_EMAIL'] = Option::get('main', 'email_from');
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }

    public function UnInstallEvents()
    {
        $dbSites = CSite::GetList($by = "sort", $order = "desc");
        while ($arSite = $dbSites->Fetch()) {
            $arSites[] = $arSite["LID"];
        }

        foreach ($arSites as $siteId) {
            $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_EVENT_TYPE]);
            $arMess = $dbMess->Fetch();
            CEventMessage::Delete($arMess['ID']);
        }

        CEventType::Delete(self::DEFAULT_EVENT_TYPE);
    }

    public function __construct()
    {
        $this->MODULE_ID = 'welpodron.feedback';
        $this->MODULE_NAME = 'Обратная связь (welpodron.feedback)';
        $this->MODULE_DESCRIPTION = 'Модуль для работы с формами обратной связи';
        $this->PARTNER_NAME = 'Welpodron';
        $this->PARTNER_URI = 'https://github.com/Welpodron';

        $this->DEFAULT_OPTIONS = [
            'BANNED_SYMBOLS' => '<,>,&,*,^,%,$,`,~,#',
            'USE_CAPTCHA' => 'N',
            'GOOGLE_CAPTCHA_SECRET_KEY' => '',
            'GOOGLE_CAPTCHA_PUBLIC_KEY' => '',
            'SUCCESS_TITLE' => 'Спасибо за заявку!',
            'SUCCESS_CONTENT' => 'Ваша заявка успешно отправлена. Мы свяжемся с Вами в ближайшее время',
            'SUCCESS_BTN_LABEL' => 'Отлично',
            'ERROR_TITLE' => 'Ошибка!',
            'ERROR_CONTENT' => 'При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта',
            'ERROR_BTN_LABEL' => 'Понятно',
        ];
    }
}
