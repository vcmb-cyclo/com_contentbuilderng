<?php \defined('_JEXEC') or die; ?>

<?php if ($repairWorkflowIsActive) : ?>
    <div class="card mt-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h3 class="h6 card-title mb-0"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_TITLE'); ?></h3>
                <small class="text-muted">
                    <?php echo Text::sprintf(
                        'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_PROGRESS',
                        min(count($repairWorkflowSteps), $repairWorkflowCurrentIndex + 1),
                        count($repairWorkflowSteps)
                    ); ?>
                </small>
            </div>

            <p class="text-muted mb-3"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_INTRO'); ?></p>

            <div class="cb-repair-workflow-steps">
                <?php foreach ($repairWorkflowSteps as $stepIndex => $repairWorkflowStep) : ?>
                    <?php
                    if (!is_array($repairWorkflowStep)) {
                        continue;
                    }

                    $stepId = (string) ($repairWorkflowStep['id'] ?? '');
                    $stepStatus = (string) ($repairWorkflowStep['status'] ?? 'pending');
                    $stepIsCurrent = $stepIndex === $repairWorkflowDisplayCurrentIndex;
                    $stepClasses = ['cb-repair-workflow-step'];
                    $statusClasses = ['cb-repair-workflow-status'];
                    $stepNumber = $getAuditSectionNumber($stepId);

                    if ($stepIsCurrent && $stepStatus === 'pending') {
                        $stepClasses[] = 'is-current';
                        $statusClasses[] = 'is-current';
                    } elseif ($stepStatus === 'done' || $stepStatus === 'skipped') {
                        $stepClasses[] = 'is-' . $stepStatus;
                        $statusClasses[] = 'is-' . $stepStatus;
                    }

                    $stepLabel = $repairWorkflowStepLabels[$stepId] ?? $stepId;
                    $stepPrecheck = (array) ($repairWorkflowStep['precheck'] ?? []);
                    $stepResult = (array) ($repairWorkflowStep['result'] ?? []);
                    $stepResultLevel = (string) ($stepResult['level'] ?? 'message');
                    $stepDescription = trim((string) ($stepPrecheck['description'] ?? ($repairWorkflowStepDescriptions[$stepId] ?? '')));
                    $statusLabelKey = match ($stepStatus) {
                        'done' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_DONE',
                        'skipped' => 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_SKIPPED',
                        default => $stepIsCurrent
                            ? 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_CURRENT'
                            : 'COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_STATUS_PENDING',
                    };
                    $statusIconClass = match (true) {
                        ($stepStatus === 'done' || $stepStatus === 'skipped') && !in_array($stepResultLevel, ['warning', 'error', 'danger'], true) => 'icon-check-circle',
                        default => '',
                    };
                    $showStepCheck = ($stepStatus === 'done' || $stepStatus === 'skipped')
                        && !in_array($stepResultLevel, ['warning', 'error', 'danger'], true);
                    ?>
                    <div class="<?php echo implode(' ', $stepClasses); ?>">
                        <div class="cb-repair-workflow-step-head">
                            <p class="cb-repair-workflow-step-title">
                                <?php if ($showStepCheck) : ?>
                                    <span class="cb-repair-workflow-step-check icon-check-circle" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars(($stepNumber > 0 ? $stepNumber . '. ' : '') . $stepLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                            <span class="<?php echo implode(' ', $statusClasses); ?>">
                                <?php if ($statusIconClass !== '') : ?>
                                    <span class="<?php echo htmlspecialchars($statusIconClass, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></span>
                                <?php endif; ?>
                                <?php echo Text::_($statusLabelKey); ?>
                            </span>
                        </div>
                        <?php if ($stepDescription !== '') : ?>
                            <p class="cb-repair-workflow-step-desc"><?php echo htmlspecialchars($stepDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                        <?php endif; ?>
                        <?php if ($stepIsCurrent && $stepStatus === 'pending') : ?>
                            <div class="alert alert-warning mt-3 mb-0">
                                <?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_CONFIRM_PROMPT'); ?>
                            </div>
                            <div class="cb-repair-workflow-actions">
                                <button
                                    type="submit"
                                    class="btn btn-success"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value=<?php echo htmlspecialchars(json_encode($stepId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>;f.elements['repair_action'].value='apply';f.elements['task'].value='about.executeRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_APPLY'); ?></button>
                                <button
                                    type="submit"
                                    class="btn btn-outline-secondary"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value=<?php echo htmlspecialchars(json_encode($stepId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>;f.elements['repair_action'].value='skip';f.elements['task'].value='about.executeRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_SKIP'); ?></button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($repairWorkflowShowCurrentPanel && is_array($repairWorkflowCurrentStep)) : ?>
                <?php
                $currentStepLabel = $repairWorkflowStepLabels[$repairWorkflowCurrentStepId] ?? $repairWorkflowCurrentStepId;
                $currentStepNumber = $getAuditSectionNumber($repairWorkflowCurrentStepId);
                $currentStepTitle = ($currentStepNumber > 0 ? $currentStepNumber . '. ' : '') . $currentStepLabel;
                $currentStepPrecheck = is_array($repairWorkflowCurrentStep) ? (array) ($repairWorkflowCurrentStep['precheck'] ?? []) : [];
                $currentStepDescription = trim((string) ($currentStepPrecheck['description'] ?? ($repairWorkflowStepDescriptions[$repairWorkflowCurrentStepId] ?? '')));
                $currentStepLines = (array) ($repairWorkflowCurrentResult['lines'] ?? []);
                $currentStepSummary = trim((string) ($repairWorkflowCurrentResult['summary'] ?? ''));
                $currentStepLevel = (string) ($repairWorkflowCurrentResult['level'] ?? 'info');
                $currentStepAlertClass = match ($currentStepLevel) {
                    'error' => 'danger',
                    'warning' => 'warning',
                    'message' => 'success',
                    default => 'info',
                };
                $currentStepPanelClasses = ['cb-repair-workflow-result'];
                if ($repairWorkflowCurrentStatus === 'skipped' || $currentStepAlertClass === 'success') {
                    $currentStepPanelClasses[] = 'is-success';
                } elseif ($currentStepAlertClass === 'warning') {
                    $currentStepPanelClasses[] = 'is-warning';
                } elseif ($currentStepAlertClass === 'danger') {
                    $currentStepPanelClasses[] = 'is-danger';
                }
                $currentStepShowCheck = $repairWorkflowCurrentStatus === 'skipped' || $currentStepAlertClass === 'success';
                ?>
                <div class="<?php echo implode(' ', $currentStepPanelClasses); ?>">
                    <h4 class="h6 mb-2 cb-repair-workflow-result-title">
                        <?php if ($currentStepShowCheck) : ?>
                            <span class="cb-repair-workflow-step-check icon-check-circle" aria-hidden="true"></span>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($currentStepTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                    </h4>
                    <?php if ($currentStepDescription !== '') : ?>
                        <p class="mb-2"><?php echo htmlspecialchars($currentStepDescription, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>

                    <?php if ($repairWorkflowCurrentStatus !== 'pending') : ?>
                        <?php if ($currentStepSummary !== '') : ?>
                            <div class="alert alert-<?php echo htmlspecialchars($currentStepAlertClass, ENT_QUOTES, 'UTF-8'); ?> mb-3">
                                <?php echo htmlspecialchars($currentStepSummary, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($currentStepLines !== []) : ?>
                            <h5 class="h6"><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_LOG_TITLE'); ?></h5>
                            <pre class="cb-repair-workflow-log border rounded p-3 mb-0"><?php echo htmlspecialchars(implode(PHP_EOL, $currentStepLines), ENT_QUOTES, 'UTF-8'); ?></pre>
                        <?php endif; ?>

                        <div class="cb-repair-workflow-actions">
                            <?php if ($repairWorkflowHasNext) : ?>
                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    onclick="var f=this.form;if(f){f.elements['repair_step'].value='';f.elements['repair_action'].value='';f.elements['task'].value='about.nextRepairWorkflowStep';}"
                                ><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT'); ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($repairWorkflowIsCompleted) : ?>
                <div class="cb-repair-workflow-summary-section">
                    <h4 class="cb-repair-workflow-summary-title">Summary</h4>
                    <div class="cb-repair-workflow-summary">
                        <span class="icon-check-circle" aria-hidden="true"></span>
                        <span><?php echo Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_FINISHED'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
