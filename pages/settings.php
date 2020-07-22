<?php
$oDefault = new nvDefaultAddons;


$form = rex_config_form::factory($oDefault->addon->name);


$field = $form->addTextAreaField('addonlist', null, ["class" => "form-control","rows" => "30"]);
$field->setLabel($this->i18n('nv_defaultaddons_addonlist'));


$fragment = new rex_fragment();
$fragment->setVar('class', 'edit', false);
$fragment->setVar('title', $this->i18n('nv_defaultaddons_settings'), false);
$fragment->setVar('body', $form->get(), false);
echo $fragment->parse('core/page/section.php');


if (rex_post($form->getName() . '_save')) {
    //$oDefault->generateFiles();
}
