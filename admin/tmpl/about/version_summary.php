<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<div class="card mt-3 cb-about-version-card">
    <div class="card-body p-3 p-lg-4">
        <div class="mb-3 cb-about-version-header">
            <h3 class="h5 mb-0 cb-about-version-title"><?php echo Text::_('COM_CONTENTBUILDERNG_VERSION_INFORMATION'); ?></h3>
            <span class="cb-about-version-meta">
                <span class="cb-about-version-badge">ContentBuilder NG</span>
                <span class="cb-about-version-badge <?php echo $isProductionBuild ? 'cb-about-version-badge--production' : 'cb-about-version-badge--dev'; ?>"><?php echo $buildTypeDisplay; ?></span>
                <span class="cb-about-platform-badges" aria-label="<?php echo htmlspecialchars(Text::_('COM_CONTENTBUILDERNG_ABOUT_PLATFORM_BADGES_LABEL'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="cb-about-platform-badge"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_PLATFORM_JOOMLA_6'); ?></span>
                    <span class="cb-about-platform-badge"><?php echo Text::_('COM_CONTENTBUILDERNG_ABOUT_PLATFORM_PHP_83'); ?></span>
                </span>
            </span>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6 col-lg-2">
                <div class="cb-about-version-tile cb-about-version-tile--version">
                    <span class="cb-about-version-icon"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></span>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($versionValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-2">
                <div class="cb-about-version-tile cb-about-version-tile--date">
                    <span class="cb-about-version-icon"><?php echo Text::_('COM_CONTENTBUILDERNG_CREATION_DATE_LABEL'); ?></span>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($creationDateValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="cb-about-version-tile cb-about-version-tile--author">
                    <span class="cb-about-version-icon"><?php echo Text::_('COM_CONTENTBUILDERNG_AUTHOR'); ?></span>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($authorValue, ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="cb-about-version-label mt-2"><?php echo Text::_('COM_CONTENTBUILDERNG_COPYRIGHT_LABEL'); ?></p>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($copyrightValue, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-12 col-lg-4">
                <div class="cb-about-version-tile cb-about-version-tile--license">
                    <span class="cb-about-version-icon"><?php echo Text::_('COM_CONTENTBUILDERNG_LICENSE_LABEL'); ?></span>
                    <p class="cb-about-version-value"><?php echo htmlspecialchars($licenseValue, ENT_QUOTES, 'UTF-8'); ?></p>
                    <a
                        class="cb-about-version-link"
                        href="<?php echo htmlspecialchars($licenseUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        target="_blank"
                        rel="noopener noreferrer"
                    ><?php echo Text::_('COM_CONTENTBUILDERNG_LICENSE_LINK'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
