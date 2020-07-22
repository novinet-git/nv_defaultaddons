<?php 

if (!$this->hasConfig("addonlist")) {
    $aAddons = array();
    $sFile = $this->getPath('lib/settings.json');
    if (file_exists($sFile)) {
        $sContent = file_get_contents($sFile);
        $aAddons = (json_decode($sContent, true));
    }
    $sAddons = json_encode($aAddons, JSON_PRETTY_PRINT);

    $this->setConfig([
        "addonlist" => $sAddons
    ]);
}