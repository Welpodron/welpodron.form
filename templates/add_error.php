<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Config\Option;

$dialogId = 'dialog_' . md5(uniqid('', false));

$moduleId = 'welpodron.feedback';
?>

<dialog data-dialog-feedback-error id="<?= $dialogId ?>" class="<?= $dialogId ?>-error">
    <div class="<?= $dialogId ?>-error__body">
        <svg width="65" height="65" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="#dc2626">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        <p><?= Option::get($moduleId, 'ERROR_TITLE') ?></p>
        <p><?= Option::get($moduleId, 'ERROR_CONTENT') ?></p>
    </div>
    <div class="<?= $dialogId ?>-error__footer">
        <button onclick="document.querySelector('#<?= $dialogId ?>').close()" class="<?= $dialogId ?>-error__btn" type="button">
            <?= Option::get($moduleId, 'ERROR_BTN_LABEL') ?>
        </button>
    </div>
    <script>
        document.querySelector("#<?= $dialogId ?>").showModal();
        document.querySelector("#<?= $dialogId ?>").addEventListener('close', () => {
            document.querySelector("#<?= $dialogId ?>").remove();
        }, {
            once: true
        })
    </script>
    <style>
        .<?= $dialogId ?>-error {
            position: fixed;
            margin: auto;
            inset: 0;
            border: 0;
            box-shadow: 0 1px 5px rgba(107, 114, 128, 0.25);
            overflow-y: auto;
            padding: 0;
            border-radius: 0;
            background: #fff;
            display: block;
            border-radius: 4px;
            max-width: 380px;
        }

        .<?= $dialogId ?>-error__body {
            display: grid;
            padding: 20px;
            place-items: center;
            text-align: center;
        }

        .<?= $dialogId ?>-error__footer {
            padding: 20px;
            display: grid;
            background: rgba(107, 114, 128, 0.05);
        }

        .<?= $dialogId ?>-error__btn {
            cursor: pointer;
            padding: 15px;
            border: 0;
            font: inherit;
            background: #dc2626;
            color: #fff;
            border-radius: 4px;
        }
    </style>
</dialog>