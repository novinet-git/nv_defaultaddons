<?php class nvDefaultAddons
{

    public static $addon_name = 'nv_defaultaddons';

    public function __construct()
    {
        $this->addon = rex_addon::get(self::$addon_name);
    }


    public static function getProjectAddons()
    {
        $oAddon = rex_addon::get(self::$addon_name);
        $aAddons = array();
        $aTmp = array();
        $sFile = $oAddon->getPath('lib/settings.json');
        $aAllAddons = rex_install_packages::getAddPackages();
        if (file_exists($sFile)) {
            $sContent = file_get_contents($sFile);
            $aTmp = (json_decode($sContent, true));
        }
        $aTmp = json_decode($oAddon->getConfig("addonlist"),true);

        foreach ($aTmp as $sKey => $sVersion) {
            if ($sVersion == "") {
                if (!isset($aAllAddons[$sKey])) {
                    echo rex_view::error('Addon '.$sKey.' ist nicht vorhanden.');
                    continue;
                }
                $aAddon = $aAllAddons[$sKey];
                $aVersions = $aAddon["files"];
                foreach ($aVersions as $iFileId => $aVersion) {
                    if (!$sVersion) {
                        $sVersion = $aVersion["version"];
                    }
                }
            }
            $aAddons[$sKey] = $sVersion;
        }
        return $aAddons;
    }

    public static function installAddons($aAddons = array())
    {
        $oAddon = rex_addon::get(self::$addon_name);
        $aError = array();
        $aSuccess = array();
        foreach ($aAddons as $sPackage => $sVersion) {

            // wenn das Addon noch nicht heruntergeladen wurde
            $package = rex_package::get($sPackage);
            if ($package instanceof rex_package) {
                $aError[] = $oAddon->i18n('addon_already_downloaded', $sPackage);
                if ($package->isInstalled()) {
                    $aError[] = $oAddon->i18n('addon_already_installed', $sPackage);
                }
                if ($package->isAvailable()) {
                    $aError[] = $oAddon->i18n('addon_already_activated', $sPackage);
                }
            }

            if ($package instanceof rex_null_package) {
                try {
                    rex_install::downloadAddon($sPackage, $sVersion);
                    $aSuccess[] = $oAddon->i18n('addon_downloaded', $sPackage);
                    $package = rex_package::get($sPackage);
                } catch (Exception $e) {
                    rex_logger::logException($e);
                    $aError[] = $oAddon->i18n('addon_exists', $sPackage);
                }
            }

            
            if (!$package->isInstalled()) {

                try {
                    $manager = rex_package_manager::factory($package);
                    $aSuccess[] = $oAddon->i18n('addon_found', $sPackage);
                } catch (rex_functional_exception $e) {
                    rex_logger::logException($e);
                    $aError[] = $e->getMessage();
                }

                try {
                    $bInstalled = $manager->install();
                    if ($bInstalled == "true") {
                        $aSuccess[] = $oAddon->i18n('addon_installed', $sPackage);
                    } else {
                        $aError[] = $oAddon->i18n('addon_failed_to_install', $sPackage);
                    }
                } catch (rex_functional_exception $e) {
                    rex_logger::logException($e);
                    $aError[] = $oAddon->i18n('addon_failed_to_install', $sPackage);
                }
            }
            if ($package->isInstalled() && !$package->isAvailable()) {
                try {
                    $ret = $manager->activate();
                    $aSuccess[] = $oAddon->i18n('addon_activated', $sPackage);
                } catch (rex_functional_exception $e) {
                    rex_logger::logException($e);
                    $aError[] = $oAddon->i18n('addon_failed_to_activate', $sPackage);
                }
            }
        }
        return $aResult = array(
            "error" => $aError,
            "success" => $aSuccess,
        );
    }

    public static function install2()
    {
        $addon = rex_addon::get(self::$addon_name);

        // in some cases rex_addon has the old package.yml in cache. But we need our new merged package.yml
        $addon->loadProperties();

        $errors = array();

        // step 1/6: select missing packages we need to download
        $missingPackages = array();
        $packages = array();
        if (isset($addon->getProperty('setup')['packages'])) {
            $packages = $addon->getProperty('setup')['packages'];
        }

        if (count($packages) > 0) {

            // fetch list of available packages from to redaxo webservice
            try {
                $packagesFromInstaller = rex_install_packages::getAddPackages();
            } catch (rex_functional_exception $e) {
                $errors[] = $e->getMessage();
                rex_logger::logException($e);
            }

            if (count($errors) == 0) {
                foreach ($packages as $id => $fileId) {

                    $localPackage = rex_package::get($id);
                    if ($localPackage->isSystemPackage()) {
                        continue; // skip system packages, they don’t need to be downloaded
                    }

                    $installerPackage = isset($packagesFromInstaller[$id]['files'][$fileId]) ? $packagesFromInstaller[$id]['files'][$fileId] : false;
                    if (!$installerPackage) {
                        $errors[] = $addon->i18n('package_not_available', $id);
                    }

                    if ($localPackage->getVersion() !== $installerPackage['version']) {
                        $missingPackages[$id] = $fileId; // add to download list if package is not yet installed
                    }
                }
            }
        }

        // step 2/6: download required packages
        if (count($missingPackages) > 0 && count($errors) == 0) {
            foreach ($missingPackages as $id => $fileId) {

                $installerPackage = $packagesFromInstaller[$id]['files'][$fileId];
                if ($installerPackage) {

                    // fetch package
                    try {
                        $archivefile = rex_install_webservice::getArchive($installerPackage['path']);
                    } catch (rex_functional_exception $e) {
                        rex_logger::logException($e);
                        $errors[] = $addon->i18n('package_failed_to_download', $id);
                        break;
                    }

                    // validate checksum
                    if ($installerPackage['checksum'] != md5_file($archivefile)) {
                        $errors[] = $addon->i18n('package_failed_to_validate', $id);
                        break;
                    }

                    // extract package (overrides local package if existent)
                    if (!rex_install_archive::extract($archivefile, rex_path::addon($id), $id)) {
                        rex_dir::delete(rex_path::addon($id));
                        $errors[] = $addon->i18n('package_failed_to_extract', $id);
                        break;
                    }

                    rex_package_manager::synchronizeWithFileSystem();
                }
            }
        }

        // step 3/6: install and activate packages based on install sequence from config
        if (count($addon->getProperty('setup')['installSequence']) > 0 && count($errors) == 0) {
            foreach ($addon->getProperty('setup')['installSequence'] as $id) {

                $package = rex_package::get($id);
                if ($package instanceof rex_null_package) {
                    $errors[] = $addon->i18n('package_not_exists', $id);
                    break;
                }

                $manager = rex_package_manager::factory($package);

                try {
                    $manager->install();
                } catch (rex_functional_exception $e) {
                    rex_logger::logException($e);
                    $errors[] = $addon->i18n('package_failed_to_install', $id);
                    break;
                }

                try {
                    $manager->activate();
                } catch (rex_functional_exception $e) {
                    rex_logger::logException($e);
                    $errors[] = $addon->i18n('package_failed_to_activate', $id);
                    break;
                }
            }
        }
        /*
        // step 4/6: import database
        if (count($addon->getProperty('setup')['dbimport']) > 0 && count($errors) == 0) {
            foreach ($addon->getProperty('setup')['dbimport'] as $import) {
                $file = rex_backup::getDir() . '/' . $import;
                $success = rex_backup::importDb($file);
                if (!$success['state']) {
                    $errors[] = $addon->i18n('package_failed_to_import', $import);
                }
            }
        }

        // step 5/6: import files
        if (count($addon->getProperty('setup')['fileimport']) > 0 && count($errors) == 0) {
            foreach ($addon->getProperty('setup')['fileimport'] as $import) {
                $file = rex_backup::getDir() . '/' . $import;
                $success = rex_backup::importFiles($file);
                if (!$success['state']) {
                    $errors[] = $addon->i18n('package_failed_to_import', $import);
                }
            }
        }
*/
        // step 6/6: make yrewrite copy its htaccess file
        if (class_exists('rex_yrewrite')) {
            rex_yrewrite::copyHtaccess();
        }

        return $errors;
    }
}
