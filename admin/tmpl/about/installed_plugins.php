<?php \defined('_JEXEC') or die; ?>

<div class="card mt-3">
    <div class="card-body p-0">
        <div class="accordion accordion-flush" id="cb-about-plugins-accordion">
            <div class="accordion-item">
                <h3 class="accordion-header" id="cb-about-plugins-heading">
                    <button
                        class="accordion-button collapsed fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#cb-about-plugins-collapse"
                        aria-expanded="false"
                        aria-controls="cb-about-plugins-collapse"
                    >
                        <?php echo Text::sprintf($pluginsCount === 1 ? 'COM_CONTENTBUILDERNG_PLUGIN_COUNT' : 'COM_CONTENTBUILDERNG_PLUGINS_COUNT', (int) $pluginsCount); ?>
                    </button>
                </h3>
                <div
                    id="cb-about-plugins-collapse"
                    class="accordion-collapse collapse"
                    aria-labelledby="cb-about-plugins-heading"
                    data-bs-parent="#cb-about-plugins-accordion"
                >
                    <div class="accordion-body">
                        <?php if (empty($this->plugins)) : ?>
                            <div class="alert alert-info mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_PLUGINS_NOT_AVAILABLE'); ?>
                            </div>
                        <?php else : ?>
                            <div class="table-responsive">
                                <table id="cb-plugins-table" class="table table-sm table-striped align-middle mb-0">
                                    <thead>
                                    <tr>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN_GROUP'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN_ELEMENT'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_JS_LIBRARY_VERSION'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN_STATUS'); ?></th>
                                        <th scope="col"><?php echo Text::_('COM_CONTENTBUILDERNG_PLUGIN_DESCRIPTION'); ?></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($this->plugins as $plugin) : ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars((string) ($plugin['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
                                                <span class="text-muted small d-block">#<?php echo (int) ($plugin['id'] ?? 0); ?></span>
                                            </td>
                                            <td><code><?php echo htmlspecialchars((string) ($plugin['group'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td><code><?php echo htmlspecialchars((string) ($plugin['element'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                            <td><?php echo htmlspecialchars((string) (($plugin['version'] ?? '') !== '' ? $plugin['version'] : Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td>
                                                <span class="badge <?php echo !empty($plugin['enabled']) ? 'bg-success' : 'bg-secondary'; ?>">
                                                    <?php echo Text::_(!empty($plugin['enabled']) ? 'COM_CONTENTBUILDERNG_PUBLISHED' : 'COM_CONTENTBUILDERNG_UNPUBLISHED'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars((string) ($plugin['description'] ?? Text::_('COM_CONTENTBUILDERNG_NOT_AVAILABLE')), ENT_QUOTES, 'UTF-8'); ?></td>
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
</div>
