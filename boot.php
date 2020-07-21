<?php $addon = rex_addon::get("nv_defaultaddons");


if (file_exists($addon->getAssetsPath("css/style.css"))) {
    rex_view::addCssFile($addon->getAssetsUrl("css/style.css"));
}
