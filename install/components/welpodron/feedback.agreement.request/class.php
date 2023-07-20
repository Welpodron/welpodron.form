<?

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\UserConsent\Agreement;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Errorable;
use Bitrix\Main\Error;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\Component\ParameterSigner;

class WelpodronFeedbackAgreementRequest extends CBitrixComponent implements Controllerable, Errorable
{
    protected $errorCollection;

    public function executeComponent()
    {
        if ($this->startResultCache($this->arParams['CACHE_TIME'], [
            $this->arParams['CACHE_GROUPS']
        ])) {
            $this->arResult = $this->getText();

            if (!($this->arParams['AGREEMENT_ID'] > 0)) {
                $this->AbortResultCache();
            }

            $this->includeComponentTemplate();
        }

        return $this->arResult;
    }

    public function getErrors()
    {
        return $this->errorCollection->toArray();
    }

    public function getErrorByCode($code)
    {
        return $this->errorCollection->getErrorByCode($code);
    }

    public function configureActions()
    {
        //если действия не нужно конфигурировать, то пишем просто так. И будет конфиг по умолчанию 
        return [
            'get' => [
                'prefilters' => []
            ]
        ];
    }

    public function getAction()
    {
        global $APPLICATION;

        $arDataRaw = $this->request->getPostList()->toArray();

        if ($arDataRaw['sessid'] !== bitrix_sessid()) {
            return;
        }

        $arDataRaw['params'] = ParameterSigner::unsignParameters($this->getName(), $arDataRaw['params']);

        if (!$arDataRaw['params']['AGREEMENT_ID'] || !$arDataRaw['params']['FIELDS']) {
            return;
        }

        ob_start();
        $APPLICATION->IncludeComponent(
            "welpodron:feedback.agreement",
            $arDataRaw['params']['AGREEMENT_TEMPLATE'] ? $arDataRaw['params']['AGREEMENT_TEMPLATE'] : '',
            [
                "AGREEMENT_ID" => $arDataRaw['params']['AGREEMENT_ID'],
                'COMPONENT_ID' => $arDataRaw['id'],
                "CACHE_GROUPS" => "N",
                "CACHE_TIME" => "0",
                "CACHE_TYPE" => "N",
                "FIELDS" => $arDataRaw['params']['FIELDS'],
            ],
            [
                "HIDE_ICONS" => "Y"
            ]
        );
        $templateIncludeResult = ob_get_contents();
        ob_end_clean();

        return $templateIncludeResult;
    }

    protected function listKeysSignedParameters()
    {
        //перечисляем те имена параметров, которые нужно использовать в аякс-действиях	
        //! Template пробрасывается для того, чтобы вызывать модальное окно соглашения				
        return [
            'AGREEMENT_ID',
            'FIELDS',
            'AGREEMENT_TEMPLATE',
        ];
    }

    public function onPrepareComponentParams($arParams)
    {
        if ($arParams['CACHE_GROUPS'] === 'N') {
            $arParams['CACHE_GROUPS'] = false;
        } else {
            $arParams['CACHE_GROUPS'] = CurrentUser::get()->getUserGroups();
        }

        $this->errorCollection = new ErrorCollection();

        $arParams['CACHE_TIME'] = isset($arParams['CACHE_TIME']) ? $arParams['CACHE_TIME'] : 36000;
        $arParams['AGREEMENT_ID'] = intval($arParams['AGREEMENT_ID']);

        $arDefault = ['IP Адрес', 'Информация о браузере пользователя', 'Идентификатор сессии пользователя'];

        $options = preg_split('/(\s*,*\s*)*,+(\s*,*\s*)*/', trim(strval($arParams['FIELDS'])));
        if ($options) {
            $arOptionsFiltered = array_filter($options, function ($value) {
                return $value !== null && $value !== '';
            });
            $arParams['FIELDS'] = array_values($arOptionsFiltered);
        } else {
            $arParams['FIELDS'] = [];
        }

        $arParams['FIELDS'] = array_merge($arParams['FIELDS'], $arDefault);
        $arParams['COMPONENT_ID'] = $arParams['COMPONENT_ID'] ? $arParams['COMPONENT_ID'] : 'agreement_' . md5(uniqid('', false));

        return $arParams;
    }

    protected function getText()
    {
        if ($this->arParams['AGREEMENT_ID'] > 0) {
            $agreement = new Agreement(
                $this->arParams['AGREEMENT_ID'],
                [
                    'FIELDS' => $this->arParams['FIELDS'],
                    'BUTTON_CAPTION' => $this->arParams['BUTTON_CAPTION'],
                ]
            );

            if (!$agreement->isExist() || !$agreement->isActive()) {
                return;
            }

            return [
                'AGREEMENT_RAW' => $agreement->getText(),
                'AGREEMENT_HTML' => $agreement->getHtml(),
                'INPUT_LABEL' =>  $this->arParams['LABEL_CAPTION'] ? $this->arParams['LABEL_CAPTION'] : $agreement->getLabelText(),
            ];
        }
    }
}
