# Модуль для работы с формами обратной связи на инфоблоках (welpodron.feedback)

> **На данный момент не рекомендуется использовать совместно с модулем форм [welpodron.form](https://github.com/Welpodron/welpodron.form)** 

Данный модуль предназначен для работы с формами обратной связи на инфоблоках:
- ленивое подключение [Google reCAPTCHA v3](https://developers.google.com/recaptcha/docs/v3)
- cписок запрещенных символов (слов)
- уведомление менеджера о заявках
- работа с пользовательскими соглашениями в обычном режиме (компонент `welpodron:feedback.agreement`) в ajax режиме (компонент `welpodron:feedback.agreement.request`)

### Содержание:

+ [Примеры использования](#EXAMPLES)
    + [Минимальная форма обратной связи с использованием пользовательского соглашения](#EXAMPLES_1)

### <a id="EXAMPLES"></a> Примеры использования:

#### <a id="EXAMPLES_1"></a> Минимальная форма обратной связи с использованием пользовательского соглашения:

```php
<?
use Bitrix\Main\Engine\UrlManager;
use Bitrix\Main\Config\Option;
?>

<form data-form <?= (Option::get('welpodron.feedback', 'USE_CAPTCHA') == "Y" ? 'data-captcha= ' . Option::get('welpodron.feedback', 'GOOGLE_CAPTCHA_PUBLIC_KEY') . '' : "") ?> action="<?= UrlManager::getInstance()->create('welpodron:feedback.controller.receiver.add') ?>">
    <div>
        <input name="firstName">
    </div>
    <div>
        <input name="tel">
    </div>
    <div>
        <input name="email">
    </div>
    <div>
        <textarea name="comment"></textarea>
    </div>
    <div>
        <? $APPLICATION->IncludeComponent(
            "welpodron:feedback.agreement.request",
            array(
                СПИСОК ВАШИХ ПАРАМЕТРОВ КОМПОНЕНТА
            ),
            false
        ); ?>
    </div>
    <button type="submit">Отправить</button>
    <div>
        <?= bitrix_sessid_post() ?>
    </div>
</form>

```

> @welpodron 2023