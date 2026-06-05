<?php
/**
 * @package     ContentBuilder NG
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

\defined('_JEXEC') or die;

class PhpTemplateHelper
{
    /**
     * Evaluate inline <?php ... ?> blocks in a string and return the result.
     * If the string does not start with <?php, it is returned unchanged.
     */
    public static function evaluate(string $result): string
    {
        if (strpos(trim($result), '<?php') !== 0) {
            return $result;
        }

        $code = trim($result);

        if (function_exists('mb_strlen')) {
            $p1 = 0;
            $l  = mb_strlen($code);
            $c  = '';
            while ($p1 < $l) {
                $p2 = mb_strpos($code, '<?php', $p1);
                if ($p2 === false) {
                    $p2 = $l;
                }
                $c  .= mb_substr($code, $p1, $p2 - $p1);
                $p1  = $p2;
                if ($p1 < $l) {
                    $p1 += 5;
                    $p2  = mb_strpos($code, '?>', $p1);
                    if ($p2 === false) {
                        $p2 = $l;
                    }
                    $c  .= eval(mb_substr($code, $p1, $p2 - $p1));
                    $p1  = $p2 + 2;
                }
            }
            return $c;
        }

        $p1 = 0;
        $l  = strlen($code);
        $c  = '';
        while ($p1 < $l) {
            $p2 = strpos($code, '<?php', $p1);
            if ($p2 === false) {
                $p2 = $l;
            }
            $c  .= substr($code, $p1, $p2 - $p1);
            $p1  = $p2;
            if ($p1 < $l) {
                $p1 += 5;
                $p2  = strpos($code, '?>', $p1);
                if ($p2 === false) {
                    $p2 = $l;
                }
                $c  .= eval(substr($code, $p1, $p2 - $p1));
                $p1  = $p2 + 2;
            }
        }
        return $c;
    }
}
