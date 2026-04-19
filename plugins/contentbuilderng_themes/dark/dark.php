<?php

/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Xavier DANO / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */


\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\Logger;

class plgContentbuilderng_themesDark extends CMSPlugin implements SubscriberInterface
{
    private const THEME_NAME = 'dark';

    private function acceptsThemeEvent(Event $event): bool
    {
        $requestedTheme = trim((string) ($event->getArgument('theme') ?? ''));

        return $requestedTheme === '' || $requestedTheme === self::THEME_NAME;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentTemplateJavascript' => 'onContentTemplateJavascript',
            'onEditableTemplateJavascript' => 'onEditableTemplateJavascript',
            'onListViewJavascript' => 'onListViewJavascript',
            'onContentTemplateCss' => 'onContentTemplateCss',
            'onEditableTemplateCss' => 'onEditableTemplateCss',
            'onListViewCss' => 'onListViewCss',
            'onContentTemplateSample' => 'onContentTemplateSample',
            'onEditableTemplateSample' => 'onEditableTemplateSample',
        ];
    }
    /**
     * Appends a value to the event result payload.
     */
    private function pushEventResult(Event $event, string $value): void
    {
        $results = $event->getArgument('result') ?: [];
        if (!is_array($results)) {
            $results = [$results];
        }
        $results[] = $value;
        $event->setArgument('result', $results);
    }

    /* =========================
     * CSS / JS events
     * ========================= */

    public function onContentTemplateJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';

        // Event dispatch mode.
        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        // Direct return mode.
        return $out;
    }

    public function onEditableTemplateJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    public function onListViewJavascript($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = '';

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    public function onContentTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = <<<'CSS'
.cbEditableWrapper {
    max-width: 1120px;
    margin: 0.7rem auto 1.4rem;
    padding: 0.85rem 0.95rem 0.95rem;
    border: 1px solid rgba(36, 61, 86, 0.12);
    border-radius: 0.85rem;
    background:
        radial-gradient(circle at top right, rgba(13, 110, 253, 0.08), transparent 38%),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 0.55rem 1.2rem rgba(16, 32, 56, 0.08);
}

.cbEditableWrapper > h1.display-6 {
    margin-bottom: 0.7rem !important;
    font-weight: 700;
    letter-spacing: 0.01em;
    font-size: clamp(1.3rem, 2.2vw, 1.85rem);
}

.cbEditableWrapper > h1.display-6::after {
    content: "";
    display: block;
    width: 3.4rem;
    height: 0.2rem;
    margin-top: 0.35rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #0d6efd 0%, #3f8cff 100%);
}

.cbEditableWrapper .cbToolBar {
    padding: 0.38rem 0.46rem;
    border: 1px solid rgba(45, 73, 104, 0.14);
    border-radius: 0.72rem;
    background: linear-gradient(180deg, #f4f7fb 0%, #e9eff6 100%);
    box-shadow:
        inset 0 1px 0 rgba(255, 255, 255, 0.84),
        0 0.18rem 0.45rem rgba(16, 32, 56, 0.06);
}

.cbEditableWrapper .cbToolBar.mb-5 {
    margin-bottom: 0.85rem !important;
}

.cbEditableWrapper .cbEditableBody {
    margin: 0.4rem 0 0.55rem;
    padding: 0.62rem 0.68rem 0.3rem;
    border: 1px solid rgba(36, 61, 86, 0.14);
    border-radius: 0.72rem;
    background: linear-gradient(180deg, #fbfdff 0%, #f2f7fc 100%);
    box-shadow: 0 0.28rem 0.72rem rgba(16, 32, 56, 0.05);
}

.cbEditableWrapper .cbColumnHeader {
    grid-template-columns: minmax(156px, 30%) minmax(0, 1fr);
    align-items: center;
    gap: 0.4rem;
    margin: 0.04rem 0 0.28rem;
    padding: 0.26rem 0.46rem;
    border: 1px solid rgba(36, 61, 86, 0.16);
    border-radius: 0.6rem;
    background: #eef4ff;
    color: #2a3f5e;
    font-size: 0.71rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}

.cbEditableWrapper .cbColumnHeader .cbColumnHeaderLabel,
.cbEditableWrapper .cbColumnHeader .cbColumnHeaderValue {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.cbEditableWrapper .cbToolBar .cbButton.btn {
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: 0.85rem;
    padding: 0.34rem 0.78rem;
    box-shadow: 0 0.2rem 0.56rem rgba(16, 32, 56, 0.11);
}

.cbEditableWrapper .cbToolBar .cbSaveButton.btn-primary,
.cbEditableWrapper .cbToolBar .cbArticleSettingsButton.btn-primary {
    border-color: #0a58ca;
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.cbEditableWrapper .cbToolBar .cbDeleteButton.btn-primary {
    border-color: #bb2d3b;
    background: linear-gradient(135deg, #dc3545 0%, #b32635 100%);
}

.cbEditableWrapper .created-by {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    margin: 0 0.3rem 0.2rem 0;
    padding: 0.14rem 0.48rem;
    border-radius: 999px;
    border: 1px solid rgba(28, 51, 78, 0.12);
    background: #eef4ff;
    color: #2d3e59;
    font-size: 0.77rem;
}

.cbEditableWrapper .alert.alert-warning {
    border: 1px solid rgba(189, 116, 0, 0.34);
    border-left-width: 0.35rem;
    border-radius: 0.8rem;
    background: linear-gradient(90deg, rgba(255, 244, 222, 0.94) 0%, rgba(255, 249, 237, 0.96) 100%);
}

.cbEditableWrapper #cbArticleOptions {
    margin-bottom: 0.65rem;
    padding: 0.2rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(36, 61, 86, 0.1);
    background: rgba(255, 255, 255, 0.72);
}

.cbEditableWrapper fieldset {
    border: 1px solid rgba(36, 61, 86, 0.14) !important;
    border-radius: 0.72rem !important;
    background: #ffffff;
    box-shadow: 0 0.18rem 0.48rem rgba(16, 32, 56, 0.05);
}

.cbEditableWrapper fieldset.border.rounded.p-3.mb-3 {
    padding: 0.52rem !important;
    margin-bottom: 0.4rem !important;
}

.cbEditableWrapper .cbEditableBody > .mb-3 {
    margin: 0 0 0.34rem !important;
    padding: 0.4rem 0.5rem;
    border: 1px solid rgba(36, 61, 86, 0.14);
    border-radius: 0.58rem;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    display: grid;
    grid-template-columns: minmax(176px, 31%) minmax(0, 1fr);
    gap: 0.46rem;
    align-items: center;
    box-shadow: 0 0.16rem 0.5rem rgba(16, 32, 56, 0.04);
}

.cbEditableWrapper .form-label,
.cbEditableWrapper label {
    font-weight: 600;
    color: #243d56;
    font-size: 0.86rem;
    margin-bottom: 0.22rem;
}

.cbEditableWrapper .cbEditableBody > .mb-3 > .form-label,
.cbEditableWrapper .cbEditableBody > .mb-3 > label.form-label {
    margin: 0 !important;
    color: #2b4a70;
    font-size: 0.74rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    line-height: 1.22;
}

.cbEditableWrapper .cbEditableBody > .mb-3 > div:last-child {
    margin: 0;
    min-width: 0;
}

.cbEditableWrapper :is(
    input[type="text"],
    input[type="email"],
    input[type="number"],
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"],
    input[type="url"],
    input[type="password"],
    textarea,
    select
) {
    border: 1px solid rgba(36, 61, 86, 0.2);
    border-radius: 0.62rem;
    background-color: #ffffff;
    font-size: 0.9rem;
    min-height: 2.05rem;
    padding: 0.34rem 0.52rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
}

.cbEditableWrapper textarea {
    min-height: 5.5rem;
    line-height: 1.35;
}

.cbEditableWrapper .form-select.form-select-sm,
.cbEditableWrapper .form-control.form-control-sm {
    min-height: 1.78rem;
    font-size: 0.84rem;
    padding-top: 0.18rem;
    padding-bottom: 0.18rem;
}

.cbEditableWrapper .cbEditableBody > .mb-3 :is(
    input[type="text"],
    input[type="email"],
    input[type="number"],
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"],
    input[type="url"],
    input[type="password"],
    textarea,
    select
) {
    min-height: 1.64rem;
    font-size: 0.83rem;
    padding: 0.2rem 0.4rem;
}

.cbEditableWrapper .cbEditableBody > .mb-3 textarea {
    min-height: 3.8rem;
}

.cbEditableWrapper :is(
    input[type="text"],
    input[type="email"],
    input[type="number"],
    input[type="date"],
    input[type="datetime-local"],
    input[type="time"],
    input[type="url"],
    input[type="password"],
    textarea,
    select
):focus {
    border-color: rgba(13, 110, 253, 0.55);
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.14);
    outline: 0;
}

.cbEditableWrapper .cbSelectField .form-select {
    display: inline-block;
    width: auto;
    max-width: 100%;
}

.cbEditableWrapper a {
    text-underline-offset: 0.15em;
}

.cbDetailsWrapper {
    max-width: 1120px;
    margin: 0.7rem auto 1.35rem;
    padding: 0.8rem 0.95rem 0.98rem;
    border: 1px solid rgba(36, 61, 86, 0.12);
    border-radius: 0.86rem;
    background:
        radial-gradient(circle at top right, rgba(13, 110, 253, 0.08), transparent 38%),
        linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
    box-shadow: 0 0.55rem 1.2rem rgba(16, 32, 56, 0.08);
}

.cbDetailsWrapper > h1.display-6 {
    margin-bottom: 0.62rem !important;
    font-weight: 700;
    letter-spacing: 0.01em;
    font-size: clamp(1.24rem, 2.05vw, 1.7rem);
}

.cbDetailsWrapper > h1.display-6::after {
    content: "";
    display: block;
    width: 3.35rem;
    height: 0.2rem;
    margin-top: 0.36rem;
    border-radius: 999px;
    background: linear-gradient(90deg, #0d6efd 0%, #3f8cff 100%);
}

.cbDetailsWrapper .cbToolBar {
    padding: 0.35rem 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
}

.cbDetailsWrapper .cbToolBar .cbButton.btn {
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: 0.84rem;
    padding: 0.32rem 0.76rem;
    box-shadow: 0 0.2rem 0.56rem rgba(16, 32, 56, 0.11);
}

.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn {
    border-radius: 999px;
    font-weight: 600;
    letter-spacing: 0.01em;
    font-size: 0.84rem;
    padding: 0.32rem 0.76rem;
    box-shadow: 0 0.2rem 0.56rem rgba(16, 32, 56, 0.11);
    display: inline-flex;
    align-items: center;
}

.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn [class^="fa-"],
.cbDetailsWrapper .cbTitleRecordNav .cbButton.btn [class*=" fa-"] {
    color: #0d6efd;
}

.cbDetailsWrapper .cbToolBar .cbEditButton.btn-primary {
    border-color: #0a58ca;
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
}

.cbDetailsWrapper .cbToolBar .cbDeleteButton.btn-primary {
    border-color: #bb2d3b;
    background: linear-gradient(135deg, #dc3545 0%, #b32635 100%);
}

.cbDetailsWrapper .created-by {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    margin: 0 0.45rem 0.3rem 0;
    padding: 0.22rem 0.62rem;
    border-radius: 999px;
    border: 1px solid rgba(28, 51, 78, 0.12);
    background: #eef4ff;
    color: #2d3e59;
}

.cbDetailsWrapper .alert.alert-warning {
    border: 1px solid rgba(189, 116, 0, 0.34);
    border-left-width: 0.35rem;
    border-radius: 0.8rem;
    background: linear-gradient(90deg, rgba(255, 244, 222, 0.94) 0%, rgba(255, 249, 237, 0.96) 100%);
}

.cbDetailsWrapper .cbDetailsBody {
    margin: 0.24rem 0 0.4rem;
    padding: 0.54rem 0.58rem 0.24rem;
    border: 1px solid rgba(36, 61, 86, 0.14);
    border-radius: 0.64rem;
    background: #ffffff;
    box-shadow: 0 0.24rem 0.62rem rgba(16, 32, 56, 0.05);
}

.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    gap: 0.3rem;
}

.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li {
    margin: 0;
    padding: 0.4rem 0.5rem;
    border: 1px solid rgba(36, 61, 86, 0.14);
    border-radius: 0.54rem;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    display: grid;
    grid-template-columns: minmax(190px, 31%) 1fr;
    gap: 0.42rem;
    align-items: start;
    box-shadow: 0 0.14rem 0.42rem rgba(16, 32, 56, 0.04);
}

.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li:nth-child(odd) {
    border-left: 0.22rem solid rgba(13, 110, 253, 0.4);
}

.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li strong.list-title {
    margin: 0;
    color: #2b4a70;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    line-height: 1.22;
}

.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li > div {
    margin: 0;
    color: #162f4d;
    font-size: 0.86rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush {
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.3rem;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item {
    margin: 0;
    padding: 0.4rem 0.48rem;
    border: 1px solid rgba(36, 61, 86, 0.14);
    border-radius: 0.54rem;
    background: linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
    box-shadow: 0 0.14rem 0.42rem rgba(16, 32, 56, 0.04);
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item:nth-child(odd) {
    border-left: 0.2rem solid rgba(13, 110, 253, 0.38);
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .row {
    --bs-gutter-x: 0.45rem;
    --bs-gutter-y: 0.1rem;
    margin: 0;
    align-items: center !important;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .col-3 {
    flex: 0 0 34%;
    max-width: 34%;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .col {
    min-width: 0;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .form-label {
    margin: 0 !important;
    color: #2b4a70;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    line-height: 1.22;
}

.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .form-control-plaintext {
    margin: 0;
    padding: 0 !important;
    color: #162f4d;
    font-size: 0.86rem;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.cbDetailsWrapper .cbDetailsBody > :last-child {
    margin-bottom: 0;
}

.cbDetailsWrapper a {
    text-underline-offset: 0.15em;
}

.cbPrintBar .btn {
    border-radius: 999px;
    box-shadow: 0 0.2rem 0.6rem rgba(16, 32, 56, 0.08);
}

@media (max-width: 767.98px) {
    .cbEditableWrapper {
        margin-top: 0.45rem;
        padding: 0.72rem 0.64rem 0.78rem;
        border-radius: 0.72rem;
    }

    .cbEditableWrapper .cbToolBar {
        padding: 0.32rem;
    }

    .cbEditableWrapper .cbToolBar .cbButton.btn {
        width: 100%;
        justify-content: center;
        font-size: 0.84rem;
    }

    .cbEditableWrapper .cbColumnHeader {
        margin-bottom: 0.45rem;
    }

    .cbEditableWrapper .cbEditableBody {
        padding: 0.56rem 0.52rem 0.24rem;
    }

    .cbEditableWrapper .cbEditableBody > .mb-3 {
        grid-template-columns: 1fr;
        gap: 0.28rem;
        padding: 0.42rem 0.46rem;
        margin-bottom: 0.3rem !important;
    }

    .cbEditableWrapper .cbEditableBody > .mb-3 > .form-label,
    .cbEditableWrapper .cbEditableBody > .mb-3 > label.form-label {
        font-size: 0.74rem;
    }

    .cbEditableWrapper fieldset.border.rounded.p-3.mb-3 {
        padding: 0.46rem !important;
        margin-bottom: 0.34rem !important;
    }

    .cbDetailsWrapper {
        margin-top: 0.45rem;
        padding: 0.72rem 0.64rem 0.78rem;
        border-radius: 0.72rem;
    }

    .cbDetailsWrapper .cbToolBar {
        padding: 0.35rem 0;
    }

    .cbDetailsWrapper .cbToolBar .cbButton.btn {
        width: 100%;
        justify-content: center;
    }

    .cbDetailsWrapper .cbDetailsBody {
        padding: 0.52rem 0.48rem 0.2rem;
    }

    .cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li {
        grid-template-columns: 1fr;
        gap: 0.28rem;
        padding: 0.44rem 0.48rem;
    }

    .cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed > li strong.list-title {
        font-size: 0.75rem;
    }

    .cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item {
        padding: 0.4rem 0.44rem;
    }

    .cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .col-3,
    .cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .col {
        flex: 0 0 100%;
        max-width: 100%;
    }

    .cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush > .list-group-item .form-control-plaintext {
        font-size: 0.82rem;
    }
}
CSS;

        $out .= <<<'CSS'
.cbEditableWrapper,
.cbDetailsWrapper{
    color:#dbe6ff;
}
.cbEditableWrapper :is(
    p,
    span,
    div,
    small,
    strong,
    em,
    li,
    dt,
    dd,
    legend,
    label,
    td,
    th
),
.cbDetailsWrapper :is(
    p,
    span,
    div,
    small,
    strong,
    em,
    li,
    dt,
    dd,
    legend,
    label,
    td,
    th
){
    color:inherit!important;
}
.cbEditableWrapper a,
.cbEditableWrapper a:visited,
.cbDetailsWrapper a,
.cbDetailsWrapper a:visited{
    color:#9fc2ff!important;
}
.cbEditableWrapper a:hover,
.cbDetailsWrapper a:hover{
    color:#d3e4ff!important;
}
.cbEditableWrapper{
    border-color:rgba(115,141,181,.28)!important;
    background:
        radial-gradient(circle at top right,rgba(123,179,255,.15),transparent 36%),
        linear-gradient(180deg,#111825 0%,#0f1521 100%)!important;
    box-shadow:0 .7rem 1.4rem rgba(1,6,16,.42)!important;
}
.cbEditableWrapper .cbToolBar,
.cbDetailsWrapper .cbToolBar{
    border-color:rgba(112,139,184,.32)!important;
    background:linear-gradient(180deg,#1b2536 0%,#141e2d 100%)!important;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.03),0 .2rem .6rem rgba(0,0,0,.4)!important;
}
.cbEditableWrapper .cbEditableBody,
.cbDetailsWrapper .cbDetailsBody{
    border-color:rgba(106,132,174,.26)!important;
    background:linear-gradient(180deg,#111a29 0%,#0d1522 100%)!important;
    box-shadow:0 .3rem .8rem rgba(2,7,18,.38)!important;
}
.cbEditableWrapper .cbColumnHeader{
    border-color:rgba(109,138,186,.32)!important;
    background:#182438!important;
    color:#9fc2ff!important;
}
.cbEditableWrapper :is(.form-control,.form-select,textarea,input[type="text"],input[type="email"],input[type="number"],input[type="date"],input[type="datetime-local"],input[type="time"],input[type="url"],input[type="password"]),
.cbDetailsWrapper :is(.form-control,.form-select,textarea,input[type="text"],input[type="email"],input[type="number"],input[type="date"],input[type="datetime-local"],input[type="time"],input[type="url"],input[type="password"]){
    background:#0d1522!important;
    border-color:#2c3b54!important;
    color:#dbe6ff!important;
}
.cbEditableWrapper :is(.form-control,.form-select,textarea)::placeholder{
    color:#8fa6cc!important;
}
.cbEditableWrapper .created-by,
.cbDetailsWrapper .created-by{
    border-color:rgba(116,142,187,.3)!important;
    background:#172338!important;
    color:#bcd3fb!important;
}
.cbEditableWrapper .alert.alert-warning,
.cbDetailsWrapper .alert.alert-warning{
    border-color:rgba(200,146,47,.46)!important;
    border-left-color:#cc8a1d!important;
    background:linear-gradient(90deg,rgba(90,58,8,.72) 0%,rgba(58,39,7,.66) 100%)!important;
    color:#ffe2a6!important;
}
.cbEditableWrapper :is(h1,h2,h3,h4,.form-label,label),
.cbDetailsWrapper :is(h1,h2,h3,h4,.list-title){
    color:#d3e2ff!important;
}
.cbEditableWrapper .cbToolBar .btn-outline-secondary,
.cbDetailsWrapper .cbToolBar .btn-outline-secondary{
    border-color:#5a7196!important;
    color:#d5e3ff!important;
}
.cbEditableWrapper .cbToolBar .btn-outline-secondary:hover,
.cbDetailsWrapper .cbToolBar .btn-outline-secondary:hover{
    background:#23324a!important;
}
.cbEditableWrapper .cbToolBar .btn-primary,
.cbDetailsWrapper .cbToolBar .btn-primary{
    border-color:#456cb2!important;
    background:linear-gradient(135deg,#1d4b91 0%,#274b85 100%)!important;
    color:#f2f7ff!important;
}
.cbDetailsWrapper .cbDetailsBody ul.category.list-striped.list-condensed>li,
.cbDetailsWrapper .cbDetailsBody .list-group.list-group-flush>.list-group-item{
    border-color:rgba(106,132,174,.28)!important;
    background:linear-gradient(180deg,#141f31 0%,#101a2a 100%)!important;
}
.cbDetailsWrapper .cbDetailsBody table,
.cbDetailsWrapper .cbDetailsBody table :is(td,th,label),
.cbEditableWrapper .cbEditableBody table,
.cbEditableWrapper .cbEditableBody table :is(td,th,label){
    color:#dbe6ff!important;
    border-color:#2b3f5f!important;
}
.cbDetailsWrapper .cbDetailsBody .form-control-plaintext{
    color:#d9e7ff!important;
}
CSS;

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    public function onEditableTemplateCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        // Comme ton original: même CSS
        return $this->onContentTemplateCss($event);
    }

    public function onListViewCss($event = null)
    {
        if ($event instanceof Event && !$this->acceptsThemeEvent($event)) {
            return;
        }

        $out = <<<'CSS'
.cb-scroll-x{overflow-x:auto;padding-bottom:.35rem;box-shadow:inset 0 -1px 0 rgba(0,0,0,.08)}
.cb-scroll-x::-webkit-scrollbar{height:12px}
.cb-scroll-x::-webkit-scrollbar-track{background:rgba(0,0,0,.06);border-radius:999px}
.cb-scroll-x::-webkit-scrollbar-thumb{background:rgba(13,110,253,.55);border-radius:999px}
.cb-scroll-x::-webkit-scrollbar-thumb:hover{background:rgba(13,110,253,.75)}
.cb-list-header{display:flex;justify-content:flex-end;align-items:center;margin:0 0 .75rem}
.cb-list-actions{display:flex;align-items:center;gap:.5rem}
.cb-list-actions .btn{border-radius:999px;padding-inline:1rem;font-weight:600}
.cb-list-panel{border:1px solid var(--bs-border-color,#dee2e6);border-radius:.9rem;padding:.65rem .75rem;background:var(--bs-body-bg,#fff);box-shadow:0 .35rem .9rem rgba(0,0,0,.06)}
.cb-list-filters td{padding:.4rem .15rem .75rem}
.cb-list-filters .form-select,.cb-list-filters .form-control{border-radius:.5rem}
.cb-list-filters .form-select:disabled,
.cb-list-filters .form-control:disabled{
    color:var(--bs-secondary-color,#6c757d);
    background-color:var(--bs-secondary-bg,#e9ecef);
    border-color:var(--bs-border-color,#dee2e6);
    opacity:1;
}
.cb-list-filters .input-group-text{border-radius:.5rem 0 0 .5rem;background:var(--bs-tertiary-bg,#f8f9fa)}
.cb-list-table{margin-top:.35rem!important}
.cb-list-table th{font-size:.875rem;letter-spacing:.01em}
.cb-list-table td,.cb-list-table th{vertical-align:middle}
.cb-list-table .hidden-phone{display:table-cell!important}
.cb-list-table select[onchange*="contentbuilderng_state_single"]{display:inline-block!important;width:auto!important;min-width:0!important;max-width:100%!important}
.cb-pagination-summary{font-weight:500}
.cb-list-titlebar{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin:0 0 .9rem;padding:.65rem .9rem;border:1px solid rgba(13,110,253,.24);border-left:.35rem solid #0d6efd;border-radius:.85rem;background:linear-gradient(90deg,rgba(13,110,253,.11),rgba(13,110,253,.03));box-shadow:0 .35rem .9rem rgba(13,110,253,.12)}
.cb-list-title{margin:0!important;font-weight:700;letter-spacing:.01em;color:#12395f}
.cb-list-title::after{content:"";display:block;width:3.75rem;height:.2rem;margin-top:.45rem;border-radius:999px;background:linear-gradient(90deg,#0d6efd,#3f8cff)}
@media (max-width:767.98px){.cb-list-actions{width:100%;justify-content:flex-end}.cb-list-panel{padding:.55rem .45rem}}
@media (max-width:767.98px){.cb-list-titlebar{padding:.55rem .65rem;margin-bottom:.75rem}.cb-list-title{font-size:1.18rem}}
CSS;

        $out .= <<<'CSS'
.cb-scroll-x{box-shadow:inset 0 -1px 0 rgba(103,131,176,.26)!important}
.cb-scroll-x::-webkit-scrollbar-track{background:rgba(125,153,196,.18)!important}
.cb-scroll-x::-webkit-scrollbar-thumb{background:rgba(103,153,231,.58)!important}
.cb-list-titlebar{
    border-color:rgba(102,132,183,.5)!important;
    border-left-color:#6fa0f8!important;
    background:linear-gradient(90deg,rgba(31,49,79,.82),rgba(21,35,59,.74))!important;
    box-shadow:0 .38rem .95rem rgba(2,8,18,.45)!important;
}
.cb-list-title{color:#d7e6ff!important}
.cb-list-title::after{background:linear-gradient(90deg,#6fa0f8,#8fbaff)!important}
.cb-list-panel{
    border-color:rgba(98,128,177,.38)!important;
    background:#121b2b!important;
    box-shadow:0 .4rem 1rem rgba(2,8,18,.44)!important;
    color:#d8e7ff!important;
}
.cb-list-panel :is(
    label,
    p,
    span,
    div,
    td,
    th,
    small,
    strong
){
    color:inherit!important;
}
.cb-list-table{
    color:#d8e7ff!important;
}
.cb-list-table thead th{
    background:linear-gradient(180deg,#1f2f47 0%,#17263d 100%)!important;
    border-color:#304764!important;
    color:#cfe1ff!important;
}
.cb-list-table tbody td{
    border-color:#263a55!important;
    background:#111a2a!important;
}
.cb-list-filters .form-control,
.cb-list-filters .form-select{
    background:#0d1522!important;
    border-color:#2f4563!important;
    color:#d8e7ff!important;
}
.cb-list-filters .form-select:disabled,
.cb-list-filters .form-control:disabled{
    color:var(--bs-secondary-color,#6c757d)!important;
    background-color:var(--bs-secondary-bg,#e9ecef)!important;
    border-color:var(--bs-border-color,#dee2e6)!important;
    opacity:1!important;
}
.cb-list-filters .input-group-text{
    background:#182538!important;
    border-color:#304864!important;
    color:#bed6ff!important;
}
.cb-list-actions .btn.btn-outline-primary{
    border-color:#6fa0f8!important;
    color:#d7e6ff!important;
}
.cb-list-actions .btn.btn-outline-primary:hover{
    background:#23324a!important;
    color:#f2f7ff!important;
}
.cb-list-actions .btn.btn-outline-secondary{
    border-color:#5a7196!important;
    color:#d5e3ff!important;
}
.cb-list-actions .btn.btn-outline-secondary:hover{
    background:#23324a!important;
}
.cb-list-actions .btn.btn-primary{
    border-color:#456cb2!important;
    background:linear-gradient(135deg,#1d4b91 0%,#274b85 100%)!important;
    color:#f2f7ff!important;
}
.cb-list-table .pagination a,
.cb-list-table .pagination span,
.cb-pagination-summary{
    color:#cfe1ff!important;
}
.cb-list-panel a,
.cb-list-panel a:visited,
.cb-list-titlebar a,
.cb-list-titlebar a:visited{
    color:#9fc2ff!important;
}
.cb-list-panel a:hover,
.cb-list-titlebar a:hover{
    color:#d3e4ff!important;
}
CSS;

        if ($event instanceof Event) {
            $this->pushEventResult($event, $out);
            return;
        }

        return $out;
    }

    /* =========================
     * Template samples
     * ========================= */

    public function onContentTemplateSample($arg0, $arg1 = null)
    {
        // Event dispatch mode: dispatch(new Event('onContentTemplateSample', [$id, $form]))
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args  = $event->getArguments();

            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;

            $out = $this->buildContentTemplateSample($contentbuilderng_form_id, $form);
            $this->pushEventResult($event, $out);
            return;
        }

        // Direct call mode: onContentTemplateSample($id, $form)
        $contentbuilderng_form_id = (int) $arg0;
        $form = $arg1;

        return $this->buildContentTemplateSample($contentbuilderng_form_id, $form);
    }

    private function buildContentTemplateSample(int $contentbuilderng_form_id, $form): string
    {
        if (!$contentbuilderng_form_id || !is_object($form)) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $elementTypes = $this->fetchElementTypes($db, $contentbuilderng_form_id, false);

        $out = '<ul class="list-group list-group-flush">' . "\n";
        $names = $form->getElementNames();

        foreach ($names as $reference_id => $name) {
            $type = $elementTypes[$reference_id] ?? null;

            if ($type !== null && $type !== 'hidden') {
                $out .= '{hide-if-empty ' . $name . '}' . "\n\n";
                $out .= '<li class="list-group-item"><div class="row g-2 align-items-start"><div class="col-3"><label class="form-label mb-0">{' . $name . ':label}</label></div><div class="col"><div class="form-control-plaintext py-0">{' . $name . ':value}</div></div></div></li>' . "\n\n";
                $out .= '{/hide}' . "\n\n";
            }
        }

        $out .= '</ul>' . "\n";
        return $out;
    }

    public function onEditableTemplateSample($arg0, $arg1 = null)
    {
        if ($arg0 instanceof Event) {
            $event = $arg0;
            if (!$this->acceptsThemeEvent($event)) {
                return;
            }
            $args  = $event->getArguments();

            $contentbuilderng_form_id = (int) ($args[0] ?? 0);
            $form = $args[1] ?? null;

            $out = $this->buildEditableTemplateSample($contentbuilderng_form_id, $form);
            $this->pushEventResult($event, $out);
            return;
        }

        $contentbuilderng_form_id = (int) $arg0;
        $form = $arg1;

        return $this->buildEditableTemplateSample($contentbuilderng_form_id, $form);
    }

    private function buildEditableTemplateSample(int $contentbuilderng_form_id, $form): string
    {
        if (!$contentbuilderng_form_id || !is_object($form)) {
            return '';
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $elementTypes = $this->fetchElementTypes($db, $contentbuilderng_form_id, true);
        if ($elementTypes === []) {
            $msg = 'No editable elements configured; generated editable sample uses all elements.';
            Factory::getApplication()->enqueueMessage($msg, 'warning');
            Log::add($msg, Log::WARNING, 'com_contentbuilderng');
            $elementTypes = $this->fetchElementTypes($db, $contentbuilderng_form_id, false);
        }

        $out = "\n";
        $names = $form->getElementNames();
        $hidden = [];

        foreach ($names as $reference_id => $name) {
            $type = $elementTypes[$reference_id] ?? null;

            if ($type === null) {
                continue;
            }

            if ($type !== 'hidden') {
                if ($type === 'checkboxgroup') {
                    $out .= '<div class="mb-3"><div class="form-label">{' . $name . ':label}</div><div>{' . $name . ':item}</div></div>';
                } elseif ($type === 'radiogroup') {
                    $out .= '<div class="mb-3"><div class="form-label">{' . $name . ':label}</div><div>{' . $name . ':item}</div></div>';
                } else {
                    $out .= '<div class="mb-3"><label class="form-label">{' . $name . ':label}</label><div>{' . $name . ':item}</div></div>' . "\n";
                }
            } else {
                $hidden[] = '{' . $name . ':item}' . "\n";
            }
        }

        foreach ($hidden as $hid) {
            $out .= $hid;
        }

        return $out;
    }

    private function fetchElementTypes(DatabaseInterface $db, int $contentbuilderng_form_id, bool $editableOnly): array
    {
        $where = "published = 1 AND form_id = " . (int) $contentbuilderng_form_id;

        if ($editableOnly) {
            $where .= " AND editable = 1";
        }

        $db->setQuery(
            "SELECT reference_id, `type`
             FROM #__contentbuilderng_elements
             WHERE " . $where
        );

        $rows = $db->loadAssocList();
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        $elementTypes = [];
        foreach ($rows as $row) {
            if (!isset($row['reference_id'])) {
                continue;
            }
            $elementTypes[$row['reference_id']] = $row['type'] ?? '';
        }

        return $elementTypes;
    }
}
