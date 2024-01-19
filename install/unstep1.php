<?

use \Bitrix\Main\Localization\Loc;

if (!check_bitrix_sessid())
    return;

?>
<form action="<?= $APPLICATION->GetCurPage() ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANG ?>">
    <input type="hidden" name="id" value="welpodron.form">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <?= CAdminMessage::ShowMessage(Loc::getMessage("MOD_UNINST_WARN")) ?>
    <p><?= Loc::getMessage("MOD_UNINST_SAVE") ?></p>
    <div class="adm-info-message">
        <b style="color:red;">Внимание!</b> При ОТКЛЮЧЕННОЙ опции "Сохранить таблицы" удаляются <b style="color:red;">ВСЕ ИНФОБЛОКИ АВТОМАТИЧЕСКИ СОЗДАВАЕМОГО В РАМКАХ МОДУЛЯ ТИПА !</b>
        В связи с чем, если вы изменили место хранения заявок по умолчанию и храните заявки в инфоблоке <b style="color:red;">ДРУГОГО ТИПА, КОТОРЫЙ НЕ СОЗДАЕТСЯ МОДУЛЕМ АВТОМАТИЧЕСКИ</b>, то вам необходимо очистить его данные <b style="color:red;">ВРУЧНУЮ</b>.
        <br>
        <br>
        <b style="color:red;">Если вы не уверены в том, что делаете, то оставьте опцию включенной.</b>
    </div>
    <p>
        <input type="checkbox" name="savedata" id="savedata" value="Y" checked>
        <label for="savedata">
            <?= Loc::getMessage("MOD_UNINST_SAVE_TABLES") ?>
        </label>
    </p>
    <input type="submit" name="" value="<?= Loc::getMessage("MOD_UNINST_DEL") ?>">
</form>