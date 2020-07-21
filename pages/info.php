<?php

$aAddons = nvDefaultAddons::getProjectAddons();



/* package info from README.md */

$content = '';

$package = rex_package::get($this->getName());
$name = $package->getPackageId();
$version = $package->getVersion();
$author = $package->getAuthor();
$supportPage = $package->getSupportPage();

if (is_readable($package->getPath('README.' . rex_i18n::getLanguage() . '.md'))) {
    $fragment = new rex_fragment();
    $fragment->setVar('content', rex_markdown::factory()->parse(rex_file::get($package->getPath('README.' . rex_i18n::getLanguage() . '.md'))), false);
    $content .= $fragment->parse('core/page/docs.php');
} elseif (is_readable($package->getPath('README.md'))) {
    $fragment = new rex_fragment();
    $fragment->setVar('content', rex_markdown::factory()->parse(rex_file::get($package->getPath('README.md'))), false);
    $content .= $fragment->parse('core/page/docs.php');
}

if (!empty($content)) {
    $fragment = new rex_fragment();
    $fragment->setVar('title', rex_i18n::msg('package_help') . ' ' . $name, false);
    $fragment->setVar('body', $content, false);
    echo $fragment->parse('core/page/section.php');
}


/* credits */

$credits = '';
$credits .= '<dl class="dl-horizontal">';
$credits .= '<dt>' . rex_i18n::msg('credits_name') . '</dt><dd>' . htmlspecialchars($name) . '</dd>';

if ($version) {
    $credits .= '<dt>' . rex_i18n::msg('credits_version') . '</dt><dd>' . $version . '</dd>';
}
if ($author) {
    $credits .= '<dt>' . rex_i18n::msg('credits_author') . '</dt><dd>' . htmlspecialchars($author) . '</dd>';
}
if ($supportPage) {
    $credits .= '<dt>' . rex_i18n::msg('credits_supportpage') . '</dt><dd><a href="' . $supportPage . '" onclick="window.open(this.href); return false;">' . $supportPage . '</a></dd>';
}

$credits .= '</dl>';

$fragment = new rex_fragment();
$fragment->setVar('title', rex_i18n::msg('credits'), false);
$fragment->setVar('body', $credits, false);
echo $fragment->parse('core/page/section.php');
