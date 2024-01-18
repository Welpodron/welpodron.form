<?

use Bitrix\Main\ModuleManager;
use Bitrix\Main\Loader;
use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Config\Option;
use Bitrix\Main\IO\Directory;

class welpodron_feedback extends CModule
{
    //! TODO: v3 ЛОКАЛИЗАЦИЯ!
    //! TODO: v3 Сделать динамические options.php похожее как при компонентах, чтобы например какие-то опции показывались только если включен определенный функционал
    //! TODO: v3 Добавить возможность в событиях полностью прерывать работу контроллера и сразу уведомлять пользователя об ошибке  
    //! TODO: v3 Добавить кастомный инсталер с возможностью уже на этапе установки выбрать готовый тип инфоблока и тд
    // marketplace fix
    var $MODULE_ID = 'welpodron.feedback';

    private $DEFAULT_OPTIONS = [];

    const DEFAULT_IBLOCK_TYPE = "welpodron_feedback";
    const DEFAULT_MAIL_EVENT_TYPE = 'WELPODRON_FEEDBACK';
    const DEFAULT_MAIL_RETURN_EVENT_TYPE = 'WELPODRON_FEEDBACK_RETURN';
    const DEFAULT_RETURN_NOTIFY_PROPERTY = 'email';

    public function InstallFiles()
    {
        global $APPLICATION;

        try {
            if (!CopyDirFiles(__DIR__ . '/js/', Application::getDocumentRoot() . '/bitrix/js', true, true)) {
                $APPLICATION->ThrowException('Не удалось скопировать js');
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
        Directory::deleteDirectory(Application::getDocumentRoot() . '/bitrix/js/welpodron.feedback');
    }

    public function DoInstall()
    {
        global $APPLICATION;

        if (!CheckVersion(ModuleManager::getVersion('main'), '14.00.00')) {
            $APPLICATION->ThrowException('Версия главного модуля ниже 14.00.00');
            return false;
        }

        if (!Loader::includeModule('welpodron.core')) {
            $APPLICATION->ThrowException('Модуль welpodron.core не был найден');
            return false;
        }

        // FIX Ранней проверки еще то установки 
        if (!Loader::includeModule("iblock")) {
            $APPLICATION->ThrowException('Не удалось подключить модуль iblock нужный для работы модуля');
            return false;
        }

        if (!$this->InstallFiles()) {
            return false;
        }

        if (!$this->InstallDb()) {
            return false;
        }

        if (!$this->InstallManagerMailEvents()) {
            return false;
        }

        if (!$this->InstallUserReturnMailEvents()) {
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
        // Пытаемся удалить модуль несмотря на любые возможные ошибки

        global $APPLICATION;

        $request = Context::getCurrent()->getRequest();

        if ($request->get("step") < 2) {
            $APPLICATION->IncludeAdminFile('Деинсталляция модуля ' . $this->MODULE_ID, __DIR__ . '/unstep1.php');
        } elseif ($request->get("step") == 2) {
            $this->UnInstallFiles();
            $this->UnInstallOptions();
            $this->UnInstallManagerMailEvents();
            $this->UnInstallUserReturnMailEvents();

            // По умолчанию данные заявок не удаляются

            if ($request->get("savedata") != "Y") {
                $this->UnInstallDB();
            }

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
            Option::delete($this->MODULE_ID);
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
                        "IBLOCK_ID" => $iblockId
                    ],
                    [
                        "NAME" => "Телефон",
                        "CODE" => "tel",
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

            $this->DEFAULT_OPTIONS['USE_SAVE'] = "Y";

            //!  CHANGE
            $this->DEFAULT_OPTIONS['RESTRICTIONS_IBLOCK_ID'] = strval($iblockId);
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

    public function InstallManagerMailEvents()
    {
        global $APPLICATION, $DB;

        try {
            $dbSites = CSite::GetList($by = "sort", $order = "desc");
            while ($arSite = $dbSites->Fetch()) {
                $arSites[] = $arSite["LID"];
            }

            foreach ($arSites as $siteId) {
                $dbEt = CEventType::GetByID(self::DEFAULT_MAIL_EVENT_TYPE, $siteId);
                $arEt = $dbEt->Fetch();

                if (!$arEt) {
                    $et = new CEventType;

                    $DB->StartTransaction();

                    $et = $et->Add([
                        'LID' => $siteId,
                        'EVENT_NAME' => self::DEFAULT_MAIL_EVENT_TYPE,
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

                $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_MAIL_EVENT_TYPE]);
                $arMess = $dbMess->Fetch();

                if (!$arMess) {
                    $mess = new CEventMessage;

                    $DB->StartTransaction();

                    $messId = $mess->Add([
                        'ACTIVE' => 'Y',
                        'EVENT_NAME' => self::DEFAULT_MAIL_EVENT_TYPE,
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
            $this->DEFAULT_OPTIONS['NOTIFY_TYPE'] = self::DEFAULT_MAIL_EVENT_TYPE;
            $this->DEFAULT_OPTIONS['NOTIFY_EMAIL'] = Option::get('main', 'email_from');
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }

    public function UnInstallManagerMailEvents()
    {
        $dbSites = CSite::GetList($by = "sort", $order = "desc");
        while ($arSite = $dbSites->Fetch()) {
            $arSites[] = $arSite["LID"];
        }

        foreach ($arSites as $siteId) {
            $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_MAIL_EVENT_TYPE]);
            $arMess = $dbMess->Fetch();
            CEventMessage::Delete($arMess['ID']);
        }

        CEventType::Delete(self::DEFAULT_MAIL_EVENT_TYPE);
    }

    // v2
    public function InstallUserReturnMailEvents()
    {
        global $APPLICATION, $DB;

        try {
            $dbSites = CSite::GetList($by = "sort", $order = "desc");
            while ($arSite = $dbSites->Fetch()) {
                $arSites[] = $arSite["LID"];
            }

            foreach ($arSites as $siteId) {
                $dbEt = CEventType::GetByID(self::DEFAULT_MAIL_RETURN_EVENT_TYPE, $siteId);
                $arEt = $dbEt->Fetch();

                if (!$arEt) {
                    $et = new CEventType;

                    $DB->StartTransaction();

                    $et = $et->Add([
                        'LID' => $siteId,
                        'EVENT_NAME' => self::DEFAULT_MAIL_RETURN_EVENT_TYPE,
                        'NAME' => 'Добавление заявки (письмо автору)',
                        'EVENT_TYPE' => 'email',
                        'DESCRIPTION'  => '
                        #email# - Email автора заявки
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

                $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_MAIL_RETURN_EVENT_TYPE]);
                $arMess = $dbMess->Fetch();

                if (!$arMess) {
                    $mess = new CEventMessage;

                    $DB->StartTransaction();

                    $messId = $mess->Add([
                        'ACTIVE' => 'Y',
                        'EVENT_NAME' => self::DEFAULT_MAIL_RETURN_EVENT_TYPE,
                        'LID' => $siteId,
                        'EMAIL_FROM' => '#DEFAULT_EMAIL_FROM#',
                        'EMAIL_TO' => '#' . self::DEFAULT_RETURN_NOTIFY_PROPERTY . '#',
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
                        Благодарим вас за оформление заявки на сайте, в ближайшее время с вами свяжется наш менеджер 
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

            $this->DEFAULT_OPTIONS['USE_RETURN_NOTIFY'] = "Y";
            $this->DEFAULT_OPTIONS['RETURN_NOTIFY_TYPE'] = self::DEFAULT_MAIL_RETURN_EVENT_TYPE;
            $this->DEFAULT_OPTIONS['RETURN_NOTIFY_PROPERTY'] = self::DEFAULT_RETURN_NOTIFY_PROPERTY;
        } catch (\Throwable $th) {
            $APPLICATION->ThrowException($th->getMessage() . '\n' . $th->getTraceAsString());
            return false;
        }

        return true;
    }
    // v2
    public function UnInstallUserReturnMailEvents()
    {
        $dbSites = CSite::GetList($by = "sort", $order = "desc");
        while ($arSite = $dbSites->Fetch()) {
            $arSites[] = $arSite["LID"];
        }

        foreach ($arSites as $siteId) {
            $dbMess = CEventMessage::GetList($by = "id", $order = "desc", ['SITE_ID' => $siteId, 'TYPE_ID' => self::DEFAULT_RETURN_NOTIFY_PROPERTY]);
            $arMess = $dbMess->Fetch();
            CEventMessage::Delete($arMess['ID']);
        }

        CEventType::Delete(self::DEFAULT_RETURN_NOTIFY_PROPERTY);
    }

    public function __construct()
    {
        $this->MODULE_ID = 'welpodron.feedback';
        $this->MODULE_NAME = 'Обратная связь (welpodron.feedback)';
        $this->MODULE_DESCRIPTION = 'Модуль для работы с формами обратной связи';
        $this->PARTNER_NAME = 'Welpodron';
        $this->PARTNER_URI = 'https://github.com/Welpodron';

        $this->DEFAULT_OPTIONS = [
            'BANNED_SYMBOLS' => '<,>,&,*,^,%,$,`,~,#,href,eval,script,/,\\,=,!,?',
            'USE_AGREEMENT_CHECK' => 'N',
            'USE_CAPTCHA' => 'N',
            'USE_SUCCESS_CONTENT' => 'Y',
            'SUCCESS_CONTENT_DEFAULT' => '<p>Спасибо за заявку, в ближайшее время с Вами свяжется наш менеджер</p>',
            'USE_ERROR_CONTENT' => 'Y',
            'ERROR_CONTENT_DEFAULT' => '<p>При обработке Вашего запроса произошла ошибка, повторите попытку позже или свяжитесь с администрацией сайта</p>',
        ];

        $arModuleVersion = [];
        include(__DIR__ . "/version.php");

        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
    }
}
