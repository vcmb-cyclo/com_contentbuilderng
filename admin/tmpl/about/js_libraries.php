<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
?>

<div class="card mt-3">
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="cb-about-js-libraries-accordion">
            <div class="accordion-item">
                <h3 class="accordion-header" id="cb-about-js-libraries-heading">
                    <button
                        class="accordion-button collapsed fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cb-about-js-libraries-collapse"
                        aria-expanded="false"
                        aria-controls="cb-about-js-libraries-collapse"
                    >
                        <?php echo Text::sprintf($javascriptLibrariesCount === 1 ? 'COM_CONTENTBUILDERNG_JS_LIBRARY_COUNT' : 'COM_CONTENTBUILDERNG_JS_LIBRARIES_COUNT', (int) $javascriptLibrariesCount); ?>
                    </button>
                </h3>
                <div
                    id="cb-about-js-libraries-collapse"
                    class="accordion-collapse collapse"
                    aria-labelledby="cb-about-js-libraries-heading"
                    data-bs-parent="#cb-about-js-libraries-accordion"
                >
                    <div class="accordion-body">
                        <?php if (empty($this->javascriptLibraries)) : ?>
                            <div class="alert alert-info mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARIES_NOT_AVAILABLE'); ?>
                            </div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table id="cb-js-libraries-table" class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_ASSETS'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_SOURCE'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($this->javascriptLibraries as $library) : ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars((string) ($library['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['version'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['assets'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars((string) ($library['source'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
