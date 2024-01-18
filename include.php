<?
CJSCore::RegisterExt('welpodron.feedback.form', [
    'js' => '/bitrix/js/welpodron.feedback/form/script.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.utils', 'welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.form.input-tel', [
    'js' => '/bitrix/js/welpodron.feedback/tel/script.js',
    'skip_core' => true
]);
