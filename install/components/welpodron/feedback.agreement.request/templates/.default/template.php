<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */
?>

<? if ($arResult) : ?>
    <label>
        <input data-agreement-id="<?= $arParams['COMPONENT_ID'] ?>" data-agreement-token="<?= bitrix_sessid() ?>" data-agreement-params="<?= $this->getComponent()->getSignedParameters() ?>" type="checkbox" required checked>
        <span><?= $arResult['INPUT_LABEL'] ?></span>
    </label>
<? endif ?>