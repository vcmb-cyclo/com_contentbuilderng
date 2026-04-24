<?php

namespace CB\Component\Contentbuilderng\Administrator\Service;

\defined('_JEXEC') or die;

class TextUtilityService
{
    public function stringURLUnicodeSlug($string): string
    {
        $str = preg_replace('/\xE3\x80\x80/', ' ', (string) $string);
        $str = str_replace('-', ' ', (string) $str);
        $str = preg_replace('#[:\#\*"@+=;!&\.%()\]\/\'\\\\|\[]#', "\x20", (string) $str);
        $str = str_replace('?', '', (string) $str);
        $str = trim(strtolower((string) $str));
        $str = preg_replace('#\x20+#', '-', $str);

        return (string) $str;
    }

    public function allhtmlspecialchars($string): string
    {
        return $this->cleanString(htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8'));
    }

    public function cleanString($string): string
    {
        return str_replace(
            ['[', ']', '{', '}', '(', ')', '|'],
            ['&#91;', '&#93;', '&#123;', '&#125;', '&#40;', '&#41;', '&#124;'],
            (string) $string
        );
    }
}
