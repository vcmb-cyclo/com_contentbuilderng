<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

use CB\Component\Contentbuilderng\Administrator\Helper\PhpTemplateHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

class RuntimeUtilityService
{
    private function getApp()
    {
        return Factory::getApplication();
    }

    public function sanitizeHiddenFilterValue(string $value): string
    {
        $value = trim(str_replace(["\r", "\n"], '', $value));

        if ($value === '') {
            return '';
        }

        if ($this->startsWithIgnoreCase($value, '$value') || $this->startsWithIgnoreCase($value, '<?php')) {
            Log::add(
                'Blocked PHP expression in hidden filter value.',
                Log::WARNING,
                'com_contentbuilderng'
            );

            return '';
        }

        $identity = $this->getApp()->getIdentity();
        $now = Factory::getDate();
        $identityId = is_object($identity) && method_exists($identity, 'get')
            ? (int) $identity->get('id', 0)
            : (int) ($identity->id ?? 0);
        $identityUsername = is_object($identity) && method_exists($identity, 'get')
            ? (string) $identity->get('username', 'anonymous')
            : (string) ($identity->username ?? 'anonymous');
        $identityName = is_object($identity) && method_exists($identity, 'get')
            ? (string) $identity->get('name', 'Anonymous')
            : (string) ($identity->name ?? 'Anonymous');

        return strtr($value, [
            '{userid}' => (string) $identityId,
            '{username}' => $identityUsername,
            '{name}' => $identityName,
            '{date}' => (string) $now->toSql(),
            '{time}' => (string) $now->format('H:i:s'),
            '{datetime}' => (string) $now->format('Y-m-d H:i:s'),
        ]);
    }

    public function getPagination($limitstart, $limit, $total)
    {
        $pages_total = 0;
        $pages_current = 0;

        if ($limit > $total) {
            $limitstart = 0;
        }

        if ($limit < 1) {
            $limit = $total;
            $limitstart = 0;
        }

        if ($limitstart > $total - $limit) {
            $limitstart = max(0, (int) (ceil($total / $limit) - 1) * $limit);
        }

        if ($limit > 0) {
            $pages_total = ceil($total / $limit);
            $pages_current = ceil(($limitstart + 1) / $limit);
        }

        $url = Uri::getInstance()->toString();
        $query = Uri::getInstance()->getQuery(true);
        unset($query['start']);

        if (count($expl_url = explode('?', $url)) > 1) {
            $impl = '';
            foreach ($query as $key => $value) {
                $impl .= $key . '=' . $value . '&';
            }
            $impl = trim($impl, '&');
            $url = $expl_url[0] . '?' . $impl;
        }

        $open = Route::_($url . (strstr($url, '?') !== false ? '&' : '?'));
        $end = '';
        $begin = '';
        $disp = !is_int($limit / 2) ? 10 : $limit;

        $start = $pages_current - ($disp / 2);
        if ($start < 1) {
            $start = 1;
        }

        $stop = $pages_total;

        if (($start + $disp) > $pages_total) {
            $stop = $pages_total;
            if ($pages_total < $disp) {
                $start = 1;
            } else {
                $start = $pages_total - $disp + 1;
                $begin = '<li><span class="pagenav">...</span></li>';
            }
        } else {
            if ($start > 1) {
                $begin = '<li><span class="pagenav">...</span></li>';
            }
            $stop = $start + $disp - 1;
            $end = '<li><span class="pagenav">...</span></li>';
        }

        $c = '';

        if ($pages_total > 1) {
            ob_start();
            ?>
            <div class="pagination">
                <ul>
                    <li class="pagination-start">
                        <?php echo $pages_current - 1 > 0 ? '<a title="' . Text::_('COM_CONTENTBUILDERNG_START') . '" href="' . $open . '" class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_START') . '</a>' : '<span class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_START') . '</span>'; ?>
                    </li>
                    <li class="pagination-prev">
                        <?php echo $pages_current - 1 > 0 ? '<a title="' . Text::_('COM_CONTENTBUILDERNG_BACK') . '" href="' . $open . 'start=' . ($limitstart - $limit) . '" class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_BACK') . '</a>' : '<span class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_BACK') . '</span>'; ?>
                    </li>
                    <?php echo $begin; ?>
                    <?php for ($i = $start; $i <= $stop; $i++) : ?>
                        <?php if ($i != $pages_current) : ?>
                            <li><a title="<?php echo $i; ?>" href="<?php echo $open; ?>start=<?php echo ($i - 1) * $limit; ?>" class="pagenav"><?php echo $i; ?></a></li>
                        <?php else : ?>
                            <li><span class="pagenav"><?php echo $i; ?></span></li>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php echo $end; ?>
                    <li class="pagination-next">
                        <?php echo $pages_current < $pages_total ? '<a title="' . Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT') . '" href="' . $open . 'start=' . ($pages_current * $limit) . '" class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT') . '</a>' : '<span class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_DB_REPAIR_WORKFLOW_NEXT') . '</span>'; ?>
                    </li>
                    <li class="pagination-end">
                        <?php echo $pages_total > 1 && $pages_current < $pages_total ? '<a title="' . Text::_('COM_CONTENTBUILDERNG_END') . '" href="' . $open . 'start=' . (($pages_total - 1) * $limit) . '" class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_END') . '</a>' : '<span class="pagenav">' . Text::_('COM_CONTENTBUILDERNG_END') . '</span>'; ?>
                    </li>
                </ul>
            </div>
            <?php
            $c = ob_get_clean();
        }

        return $c;
    }

    public function execPhp($result): string
    {
        return PhpTemplateHelper::evaluate((string) $result);
    }

    private function startsWithIgnoreCase(string $value, string $prefix): bool
    {
        return strncasecmp($value, $prefix, strlen($prefix)) === 0;
    }
}
