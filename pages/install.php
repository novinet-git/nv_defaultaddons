<?php

$aAddons = nvDefaultAddons::getProjectAddons();

if (rex_post('install', 'boolean')) {

    $aResult = nvDefaultAddons::installAddons($aAddons);
    $aError = $aResult["error"];
    $aSuccess = $aResult["success"];


    // show result messages
    if (count($aError) > 0) {
        echo rex_view::error("<p>" . $this->i18n('installation_error') . "</p><ul><li>" . implode("</li><li>", $aError) . "</li></ul>");
    }
    if (!count($aError) or count($aSuccess) > 0) {
        echo rex_view::success("<p>" . $this->i18n('installation_success') . "</p><ul><li>" . implode("</li><li>", $aSuccess) . "</li></ul>");
    }
}


/* setup info */
if (!count($aAddons)) {
    echo rex_view::error("<p>" . $this->i18n('error_no_addons') . "</p>");
}
$content = '<p>' . $this->i18n('install_description') . '</p>';
if (count($aAddons)) {
$content .= '<p><b>Folgende Addons sollen installiert werden:</b></p><ul>';

$errors = array();

foreach ($aAddons as $sPackage => $sVersion) {
    $oPackage = rex_package::get($sPackage);
    $content .= '<li>' . $sPackage . ' (' . $sVersion . ')';
    if ($oPackage->isAvailable()) {
        $content .= ' - bereits aktiv';
    }
    $content .= '</li>';
}
$content .= '</ul><br>';


    $content .= '<p><button class="btn btn-send" type="submit" name="install" value="1"><i class="rex-icon fa-download"></i> ' . $this->i18n('install_button') . '</button></p>';
}
$fragment = new rex_fragment();
$fragment->setVar('title', $this->i18n('install_heading'), false);
$fragment->setVar('body', $content, false);
$content = $fragment->parse('core/page/section.php');

$content = '
<form action="' . rex_url::currentBackendPage() . '" method="post" data-confirm="' . $this->i18n('confirm_setup') . '">
    ' . $content . '
</form>';

echo $content;