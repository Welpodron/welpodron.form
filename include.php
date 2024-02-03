<?

use Bitrix\Main\Loader;

Loader::includeModule("welpodron.core");

CJSCore::RegisterExt('welpodron.form', [
    'js' => '/local/packages/welpodron.form/iife/form/index.js',
    'skip_core' => true,
    'rel' => ['welpodron.core.utils', 'welpodron.core.templater'],
]);

CJSCore::RegisterExt('welpodron.form.inputs.tel', [
    'js' => '/local/packages/welpodron.form/iife/inputs/_tel/index.js',
    'skip_core' => true
]);

CJSCore::RegisterExt('welpodron.form.inputs.number', [
    'js' => '/local/packages/welpodron.form/iife/inputs/_number/index.js',
    'skip_core' => true
]);

CJSCore::RegisterExt('welpodron.form.inputs.file', [
    'js' => '/local/packages/welpodron.form/iife/inputs/_file/index.js',
    'skip_core' => true
]);

CJSCore::RegisterExt('welpodron.form.inputs.calendar', [
    'js' => '/local/packages/welpodron.form/iife/inputs/_calendar/index.js',
    'skip_core' => true
]);
