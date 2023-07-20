<?

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\UserConsent\Agreement;

class WelpodronFeedbackAgreement extends CBitrixComponent
{
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

    public function onPrepareComponentParams($arParams)
    {
        if ($arParams['CACHE_GROUPS'] === 'N') {
            $arParams['CACHE_GROUPS'] = false;
        } else {
            $arParams['CACHE_GROUPS'] = CurrentUser::get()->getUserGroups();
        }

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
            $agreement = new Agreement($this->arParams['AGREEMENT_ID'], [
                'FIELDS' => $this->arParams['FIELDS'],
                'BUTTON_CAPTION' => $this->arParams['BUTTON_CAPTION'],
            ]);

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
