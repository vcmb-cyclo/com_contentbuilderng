<?php

/**
 * @package     ContentBuilderNG
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */

declare(strict_types=1);

\defined('_JEXEC') or die;
?>
<main class="container py-4 cbstats-syntax-help">
    <header class="mb-4">
        <h1 class="h2">
            <span class="fa-solid fa-circle-question me-2" aria-hidden="true"></span>
            <?php echo htmlspecialchars($this->pageTitle, ENT_QUOTES, 'UTF-8'); ?>
        </h1>
        <p class="lead mb-0">
            <?php echo htmlspecialchars($this->summary, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </header>

    <nav class="cb-help-toc" aria-label="Help sections">
        <?php foreach ($this->sections as $sectionIndex => $section): ?>
            <a href="#cb-help-section-<?php echo (int) $sectionIndex; ?>"><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($this->sections as $sectionIndex => $section): ?>
        <section class="card mb-4 cb-help-section" id="cb-help-section-<?php echo (int) $sectionIndex; ?>">
            <div class="card-body">
                <?php if ($sectionIndex > 0): ?>
                    <h2 class="h4 card-title">
                        <?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>
                <?php endif; ?>
                <div class="card-text">
                    <?php echo $section['content']; ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>
</main>
<style>.cbstats-syntax-help h1{font-size:1.2rem;line-height:1.25}.cbstats-syntax-help header{margin-bottom:.55rem!important}.cbstats-syntax-help header .lead{font-size:1rem;line-height:1.35}.cb-help-toc{display:flex;flex-wrap:wrap;gap:.15rem;padding:.25rem .4rem;margin-bottom:.65rem;background:var(--bs-body-bg);border:1px solid var(--bs-border-color);border-radius:.5rem}.cb-help-toc a{padding:.2rem .45rem;border-radius:999px;background:var(--bs-tertiary-bg);font-size:.9rem;line-height:1.2;text-decoration:none}.cb-help-section{scroll-margin-top:4rem}.cb-help-section .card-body{padding:.8rem 1rem}.cb-help-section .card-title{font-size:1.1rem}</style>
