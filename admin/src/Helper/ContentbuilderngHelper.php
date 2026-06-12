<?php

/**
 * @package     ContentBuilderNG
 * @author      Markus Bopp
 * @author      XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * SPDX-License-Identifier: GPL-2.0-or-later
 */


namespace CB\Component\Contentbuilderng\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * includes a scands chars fix from user jajusain
 * 
 * POST:
 * http://crosstec.de/en/forums/37-contentbuilder-general-forum/63712-scands-bug.html?limit=6&start=12#63770
 *
 * @param type $path
 * @return boolean 
 */


if (!function_exists('mb_wordwrap')) {
    function mb_wordwrap($str, $width = 74, $break = "\r\n")
    {
        // Return short or empty strings untouched
        if (empty($str) || mb_strlen($str, 'UTF-8') <= $width)
            return $str;

        $br_width  = mb_strlen($break, 'UTF-8');
        $str_width = mb_strlen($str, 'UTF-8');
        $return = '';
        $last_space = false;

        for ($i = 0, $count = 0; $i < $str_width; $i++, $count++) {
            // If we're at a break
            if (mb_substr($str, $i, $br_width, 'UTF-8') == $break) {
                $count = 0;
                $return .= mb_substr($str, $i, $br_width, 'UTF-8');
                $i += $br_width - 1;
                continue;
            }

            // Keep a track of the most recent possible break point
            if (mb_substr($str, $i, 1, 'UTF-8') == " ") {
                $last_space = $i;
            }

            // It's time to wrap
            if ($count > $width) {
                // There are no spaces to break on!  Going to truncate :(
                if (!$last_space) {
                    $return .= $break;
                    $count = 0;
                } else {
                    // Work out how far back the last space was
                    $drop = $i - $last_space;

                    // Cutting zero chars results in an empty string, so don't do that
                    if ($drop > 0) {
                        $return = mb_substr($return, 0, -$drop);
                    }

                    // Add a break
                    $return .= $break;

                    // Update pointers
                    $i = $last_space + ($br_width - 1);
                    $last_space = false;
                    $count = 0;
                }
            }

            // Add character from the input string to the output
            $return .= mb_substr($str, $i, 1, 'UTF-8');
        }
        return $return;
    }
}


class ContentbuilderngHelper
{

    public static function contentbuilderng_wordwrap($str, $width = 75, $break = "\n", $cut = false, $charset = null)
    {
        if (function_exists('mb_strlen')) {
            return mb_wordwrap($str, $width, $break, $cut, $charset);
        } else {
            return wordwrap($str, $width, $break, $cut);
        }
    }

    private static function is_url($url = FALSE)
    {
        $info = parse_url($url);
        return ((isset($info['scheme']) && $info['scheme'] == 'http') || (isset($info['scheme']) && $info['scheme'] == 'https') || (isset($info['scheme']) && $info['scheme'] == 'ftp')) && isset($info['host']) && $info['host'] != "";
    }

    public static function is_internal_path($path)
    {
        $path = (string) $path;

        if (strpos(strtolower($path), '{cbsite}') === 0) {
            $path = str_replace(array('{cbsite}', '{CBSite}'), array(JPATH_SITE, JPATH_SITE), $path);
        }

        if (self::is_url($path)) {
            return false;
        }

        $path = trim(str_replace('\\', '/', $path));
        if ($path === '') {
            return false;
        }

        $siteRoot = realpath(JPATH_SITE) ?: JPATH_SITE;
        $siteRoot = rtrim(str_replace('\\', '/', $siteRoot), '/');

        $isAbsolute = strpos($path, '/') === 0 || (bool) preg_match('#^[A-Za-z]:/#', $path);
        $candidate = $isAbsolute ? $path : ($siteRoot . '/' . ltrim($path, '/'));
        $candidate = str_replace('\\', '/', $candidate);

        $real = @realpath($candidate);
        if ($real === false) {
            $realParent = @realpath(dirname($candidate));
            if ($realParent === false) {
                return false;
            }
            $real = rtrim(str_replace('\\', '/', $realParent), '/') . '/' . basename($candidate);
        } else {
            $real = str_replace('\\', '/', $real);
        }

        return strncasecmp($real, $siteRoot . '/', strlen($siteRoot) + 1) === 0 || strcasecmp($real, $siteRoot) === 0;
    }

    public static function cbinternal($value)
    {
        $nl = '';
        $out = '';
        // XDA-GIL - Deprecated NULL values
        $values = explode("\n", '' . $value);
        $length = count($values);
        $i = 0;
        foreach ($values as $_value) {
            if ($i + 1 < $length) {
                $nl = "\n";
            } else {
                $nl = '';
            }
            $out .= (self::is_internal_path($_value) ? basename($_value) : $_value) . $nl;
            $i++;
        }

        return $out;
    }

    public static function isEmail($email, $checkDNS = false)
    {
        //      Check that $email is a valid address
        //              (http://tools.ietf.org/html/rfc3696)
        //              (http://tools.ietf.org/html/rfc2822)
        //              (http://tools.ietf.org/html/rfc5322#section-3.4.1)
        //              (http://tools.ietf.org/html/rfc5321#section-4.1.3)
        //              (http://tools.ietf.org/html/rfc4291#section-2.2)
        //              (http://tools.ietf.org/html/rfc1123#section-2.1)

        //      the upper limit on address lengths should normally be considered to be 256
        //              (http://www.rfc-editor.org/errata_search.php?rfc=3696)
        if (strlen($email) > 256)       return false;   //      Too long

        //      Contemporary email addresses consist of a "local part" separated from
        //      a "domain part" (a fully-qualified domain name) by an at-sign ("@").
        //              (http://tools.ietf.org/html/rfc3696#section-3)
        $index = strrpos($email, '@');

        if ($index === false)           return false;   //      No at-sign
        if ($index === 0)               return false;   //      No local part
        if ($index > 64)                return false;   //      Local part too long

        $localPart              = substr($email, 0, $index);
        $domain                 = substr($email, $index + 1);
        $domainLength   = strlen($domain);

        if ($domainLength === 0)        return false;   //      No domain part
        if ($domainLength > 255)        return false;   //      Domain part too long

        //      Let's check the local part for RFC compliance...
        //
        //      local-part      =       dot-atom / quoted-string / obs-local-part
        //      obs-local-part  =       word *("." word)
        //              (http://tools.ietf.org/html/rfc2822#section-3.4.1)
        if (preg_match('/^"(?:.)*"$/', $localPart) > 0) {
            $dotArray[]     = $localPart;
        } else {
            $dotArray       = explode('.', $localPart);
        }

        foreach ($dotArray as $localElement) {
            //      Period (".") may...appear, but may not be used to start or end the
            //      local part, nor may two or more consecutive periods appear.
            //              (http://tools.ietf.org/html/rfc3696#section-3)
            //
            //      A zero-length element implies a period at the beginning or end of the
            //      local part, or two periods together. Either way it's not allowed.
            if ($localElement === '')                                                                               return false;   //      Dots in wrong place

            //      Each dot-delimited component can be an atom or a quoted string
            //      (because of the obs-local-part provision)
            if (preg_match('/^"(?:.)*"$/', $localElement) > 0) {
                //      Quoted-string tests:
                //
                //      Note that since quoted-pair
                //      is allowed in a quoted-string, the quote and backslash characters may
                //      appear in a quoted-string so long as they appear as a quoted-pair.
                //              (http://tools.ietf.org/html/rfc2822#section-3.2.5)
                $groupCount     = preg_match_all('/(?:^"|"$|\\\\\\\\|\\\\")|(\\\\|")/', $localElement, $matches);
                array_multisort($matches[1], SORT_DESC);
                if ($matches[1][0] !== '')                                                                      return false;   //      Unescaped quote or backslash character inside quoted string
                if (preg_match('/^"\\\\*"$/', $localElement) > 0)                       return false;   //      "" and "\" are slipping through - note: must tidy this up
            } else {
                //      Unquoted string tests:
                //
                //      Any ASCII graphic (printing) character other than the
                //      at-sign ("@"), backslash, double quote, comma, or square brackets may
                //      appear without quoting.  If any of that list of excluded characters
                //      are to appear, they must be quoted
                //              (http://tools.ietf.org/html/rfc3696#section-3)
                //
                $stripped = '';
                //      Any excluded characters? i.e. <space>, @, [, ], \, ", <comma>
                if (preg_match('/[ @\\[\\]\\\\",]/', $localElement) > 0)
                    //      Check all excluded characters are escaped
                    $stripped = preg_replace('/\\\\[ @\\[\\]\\\\",]/', '', $localElement);
                if (preg_match('/[ @\\[\\]\\\\",]/', $stripped) > 0)    return false;   //      Unquoted excluded characters
            }
        }

        //      Now let's check the domain part...

        //      The domain name can also be replaced by an IP address in square brackets
        //              (http://tools.ietf.org/html/rfc3696#section-3)
        //              (http://tools.ietf.org/html/rfc5321#section-4.1.3)
        //              (http://tools.ietf.org/html/rfc4291#section-2.2)
        if (preg_match('/^\\[(.)+]$/', $domain) === 1) {
            //      It's an address-literal
            $addressLiteral = substr($domain, 1, $domainLength - 2);
            $matchesIP              = array();

            //      Extract IPv4 part from the end of the address-literal (if there is one)
            if (preg_match('/\\b(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/', $addressLiteral, $matchesIP) > 0) {
                $index = strrpos($addressLiteral, $matchesIP[0]);

                if ($index === 0) {
                    //      Nothing there except a valid IPv4 address, so...
                    return true;
                } else {
                    //      Assume it's an attempt at a mixed address (IPv6 + IPv4)
                    if ($addressLiteral[$index - 1] !== ':')                        return false;   //      Character preceding IPv4 address must be ':'
                    if (substr($addressLiteral, 0, 5) !== 'IPv6:')          return false;   //      RFC5321 section 4.1.3

                    $IPv6 = substr($addressLiteral, 5, ($index === 7) ? 2 : $index - 6);
                    $groupMax = 6;
                }
            } else {
                //      It must be an attempt at pure IPv6
                if (substr($addressLiteral, 0, 5) !== 'IPv6:')                  return false;   //      RFC5321 section 4.1.3
                $IPv6 = substr($addressLiteral, 5);
                $groupMax = 8;
            }

            $groupCount     = preg_match_all('/^[0-9a-fA-F]{0,4}|\\:[0-9a-fA-F]{0,4}|(.)/', $IPv6, $matchesIP);
            $index          = strpos($IPv6, '::');

            if ($index === false) {
                //      We need exactly the right number of groups
                if ($groupCount !== $groupMax)                                                  return false;   //      RFC5321 section 4.1.3
            } else {
                if ($index !== strrpos($IPv6, '::'))                                             return false;   //      More than one '::'
                $groupMax = ($index === 0 || $index === (strlen($IPv6) - 2)) ? $groupMax : $groupMax - 1;
                if ($groupCount > $groupMax)                                                    return false;   //      Too many IPv6 groups in address
            }

            //      Check for unmatched characters
            array_multisort($matchesIP[1], SORT_DESC);
            if ($matchesIP[1][0] !== '')                                                            return false;   //      Illegal characters in address

            //      It's a valid IPv6 address, so...
            return true;
        } else {
            //      It's a domain name...

            //      The syntax of a legal Internet host name was specified in RFC-952
            //      One aspect of host name syntax is hereby changed: the
            //      restriction on the first character is relaxed to allow either a
            //      letter or a digit.
            //              (http://tools.ietf.org/html/rfc1123#section-2.1)
            //
            //      NB RFC 1123 updates RFC 1035, but this is not currently apparent from reading RFC 1035.
            //
            //      Most common applications, including email and the Web, will generally not permit...escaped strings
            //              (http://tools.ietf.org/html/rfc3696#section-2)
            //
            //      Characters outside the set of alphabetic characters, digits, and hyphen MUST NOT appear in domain name
            //      labels for SMTP clients or servers
            //              (http://tools.ietf.org/html/rfc5321#section-4.1.2)
            //
            //      RFC5321 precludes the use of a trailing dot in a domain name for SMTP purposes
            //              (http://tools.ietf.org/html/rfc5321#section-4.1.2)
            $matches        = array();
            $groupCount     = preg_match_all('/(?:[0-9a-zA-Z][0-9a-zA-Z-]{0,61}[0-9a-zA-Z]|[a-zA-Z])(?:\\.|$)|(.)/', $domain, $matches);
            $level          = count($matches[0]);

            if ($level == 1)                                                                                        return false;   //      Mail host can't be a TLD

            $TLD = $matches[0][$level - 1];
            if (substr($TLD, strlen($TLD) - 1, 1) === '.')                          return false;   //      TLD can't end in a dot
            if (preg_match('/^[0-9]+$/', $TLD) > 0)                                         return false;   //      TLD can't be all-numeric

            //      Check for unmatched characters
            array_multisort($matches[1], SORT_DESC);
            if ($matches[1][0] !== '')                                                                      return false;   //      Illegal characters in domain, or label longer than 63 characters

            //      Check DNS?
            if ($checkDNS && function_exists('checkdnsrr')) {
                if (!(checkdnsrr($domain, 'A') || checkdnsrr($domain, 'MX'))) {
                    return false;   //      Domain doesn't actually exist
                }
            }

            //      Eliminate all other factors, and the one which remains must be the truth.
            //              (Sherlock Holmes, The Sign of Four)
            return true;
        }
    }

    public static function isValidDate($value, $format = 'YYYY-mm-dd')
    {
        $format = $format = str_replace('%', '', $format);

        if (strlen($value) >= 4 && strlen($format)) {

            // find separator. Remove all other characters from $format
            $separator_only = str_replace(array('m', 'd', 'y'), '', strtolower($format));
            $separator = $separator_only[0]; // separator is first character

            $separator_only2 = preg_replace("/[0-9]/", '', strtolower($value));
            $separator2 = $separator_only2[0]; // separator is first character

            if ($separator && strlen($separator_only) == 2) {

                $value_exploded  = explode($separator2, $value);
                $format_exploded = explode($separator, $format);

                $yearindex = 0;
                $monthindex = 0;
                $dayindex = 0;

                $i = 0;
                foreach ($format_exploded as $form) {
                    if (strstr(strtolower($form), 'y') !== false) {
                        $yearindex = $i;
                    }
                    if (strstr(strtolower($form), 'm') !== false) {
                        $monthindex = $i;
                    }
                    if (strstr(strtolower($form), 'd') !== false) {
                        $dayindex = $i;
                    }
                    $i++;
                }

                if (!is_numeric($value_exploded[$monthindex])) {
                    return false;
                }

                if (!is_numeric($value_exploded[$dayindex])) {
                    return false;
                }

                if (!is_numeric($value_exploded[$yearindex])) {
                    return false;
                }

                if (@checkdate($value_exploded[$monthindex], $value_exploded[$dayindex], $value_exploded[$yearindex])) {
                    return true;
                }
            }
        }
        return false;
    }


    public static function convertDate($value, $srcFormat = 'YYYY-mm-dd', $format = 'YYYY-mm-dd')
    {

        $format    = str_replace('%', '', $format);
        $srcFormat = str_replace('%', '', $srcFormat);

        if (strlen($value) >= 4 && strlen($format)) {

            // find separator. Remove all other characters from $format
            $separator_only = str_replace(array('m', 'd', 'y'), '', strtolower($format));
            $separator = $separator_only[0]; // separator is first character

            $separator_only2 = preg_replace("/[0-9]/", '', strtolower($value));
            $separator2 = $separator_only2[0]; // separator is first character

            $separator_only3 = str_replace(array('m', 'd', 'y'), '', strtolower($srcFormat));
            $separator3 = $separator_only2[0]; // separator is first character

            if ($separator && strlen($separator_only) == 2) {

                $value_exploded  = explode($separator2, $value);
                $format_exploded = explode($separator, $format);
                $srcformat_exploded = explode($separator3, $srcFormat);

                $srcyearindex = 0;
                $srcmonthindex = 0;
                $srcdayindex = 0;

                $yearindex = 0;
                $monthindex = 0;
                $dayindex = 0;

                $i = 0;
                foreach ($srcformat_exploded as $form) {
                    if (strstr(strtolower($form), 'y') !== false) {
                        $srcyearindex = $i;
                    }
                    if (strstr(strtolower($form), 'm') !== false) {
                        $srcmonthindex = $i;
                    }
                    if (strstr(strtolower($form), 'd') !== false) {
                        $srcdayindex = $i;
                    }
                    $i++;
                }

                $i = 0;
                foreach ($format_exploded as $form) {
                    if (strstr(strtolower($form), 'y') !== false) {
                        $yearindex = $i;
                    }
                    if (strstr(strtolower($form), 'm') !== false) {
                        $monthindex = $i;
                    }
                    if (strstr(strtolower($form), 'd') !== false) {
                        $dayindex = $i;
                    }
                    $i++;
                }

                if (!is_numeric($value_exploded[$srcmonthindex])) {
                    return $value;
                }

                if (!is_numeric($value_exploded[$srcdayindex])) {
                    return $value;
                }

                if (!is_numeric($value_exploded[$srcyearindex])) {
                    return $value;
                }

                if (strlen(intval($value_exploded[$srcyearindex])) < 4) {
                    $yearlen = strlen(intval($value_exploded[$srcyearindex]));
                    if ($yearlen == 3) {
                        $value_exploded[$srcyearindex] = '0' . intval($value_exploded[$srcyearindex]);
                    } else if ($yearlen == 2) {
                        $value_exploded[$srcyearindex] = '00' . intval($value_exploded[$srcyearindex]);
                    } else if ($yearlen == 1) {
                        $value_exploded[$srcyearindex] = '000' . intval($value_exploded[$srcyearindex]);
                    }
                }

                if (strlen(intval($value_exploded[$srcmonthindex])) < 2) {
                    $yearlen = strlen(intval($value_exploded[$srcmonthindex]));
                    if ($yearlen == 1) {
                        $value_exploded[$srcmonthindex] = '0' . intval($value_exploded[$srcmonthindex]);
                    }
                }

                if (strlen(intval($value_exploded[$srcdayindex])) < 2) {
                    $yearlen = strlen(intval($value_exploded[$srcdayindex]));
                    if ($yearlen == 1) {
                        $value_exploded[$srcdayindex] = '0' . intval($value_exploded[$srcdayindex]);
                    }
                }


                $out_value_exploded = array();

                $out_value_exploded[intval($yearindex)] = $value_exploded[$srcyearindex];
                $out_value_exploded[intval($monthindex)] = $value_exploded[$srcmonthindex];
                $out_value_exploded[intval($dayindex)] = $value_exploded[$srcdayindex];

                ksort($out_value_exploded);

                $out = '';
                foreach ($out_value_exploded as $valex) {
                    $out .= $valex . $separator;
                }

                $out = rtrim($out, $separator);

                return $out;
            }
        }
        return $value;
    }

    public static function listIncludeInList($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark', $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->list_include),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'list_include',
            'no_list_include',
            'COM_CONTENTBUILDERNG_LIST_INCLUDE',
            'COM_CONTENTBUILDERNG_NO_LIST_INCLUDE'
        );
    }

    public static function listIncludeInSearch($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark', $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->search_include),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'search_include',
            'no_search_include',
            'COM_CONTENTBUILDERNG_SEARCH_INCLUDE',
            'COM_CONTENTBUILDERNG_NO_SEARCH_INCLUDE'
        );
    }

    public static function listLinkable($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark', $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->linkable),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'linkable',
            'not_linkable',
            'COM_CONTENTBUILDERNG_LINKABLE',
            'COM_CONTENTBUILDERNG_NOT_LINKABLE'
        );
    }

    public static function listEditable($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->editable),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'editable',
            'not_editable',
            'COM_CONTENTBUILDERNG_EDITABLE',
            'COM_CONTENTBUILDERNG_NOT_EDITABLE'
        );
    }

    public static function listApiAllowed($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->api_allowed),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'api_allowed',
            'not_api_allowed',
            'COM_CONTENTBUILDERNG_API_ALLOWED',
            'COM_CONTENTBUILDERNG_NOT_API_ALLOWED'
        );
    }

    public static function listVerifiedView($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->verified_view),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'verified_view',
            'not_verified_view',
            'COM_CONTENTBUILDERNG_VERIFIED_VIEW',
            'COM_CONTENTBUILDERNG_UNVERIFIED_VIEW'
        );
    }

    public static function listVerifiedNew($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->verified_new),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'verified_new',
            'not_verified_new',
            'COM_CONTENTBUILDERNG_VERIFIED_NEW',
            'COM_CONTENTBUILDERNG_UNVERIFIED_NEW'
        );
    }

    public static function listVerifiedEdit($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return self::renderBooleanStateToggle(
            !empty($row->verified_edit),
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'verified_edit',
            'not_verified_edit',
            'COM_CONTENTBUILDERNG_VERIFIED_EDIT',
            'COM_CONTENTBUILDERNG_UNVERIFIED_EDIT'
        );
    }

    public static function listPublish($domain, $row, $i, $publish_icon = 'fa-solid fa-check', $unpublish_icon = 'fa-solid fa-circle-xmark',  $prefix = '')
    {
        return HTMLHelper::_(
            'jgrid.published',
            !empty($row->published) ? 1 : 0,
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            true,
            'cb'
        );
    }

    public static function listDebug($domain, $row, $i, $prefix = '')
    {
        $enabled = !empty($row->debug_mode);
        $toggle = self::renderBooleanStateToggle(
            $enabled,
            (int) $i,
            (string) $prefix . (string) $domain . '.',
            'debug_on',
            'debug_off',
            'COM_CONTENTBUILDERNG_DEBUG_ON',
            'COM_CONTENTBUILDERNG_DEBUG_OFF'
        );

        if ($enabled) {
            $toggle = str_replace('icon-publish', 'fa fa-bug text-success', $toggle);
        }

        return $toggle;
    }

    public static function publishButton($published, $url_publish, $url_unpublish, $imgY = 'tick.png', $imgX = 'publish_x.png', $allowed = true)
    {
        $isPublished = (bool) $published;
        $url = $isPublished ? (string) $url_unpublish : (string) $url_publish;
        $action = $isPublished ? Text::_('COM_CONTENTBUILDERNG_UNPUBLISH') : Text::_('COM_CONTENTBUILDERNG_PUBLISH');
        $iconClass = $isPublished ? 'publish' : 'unpublish';
        $iconHtml = '<span class="fa-' . $iconClass . '" aria-hidden="true"></span>'
            . '<span class="visually-hidden">' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '</span>';

        if ($allowed) {
            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" class="tbody-icon'
                . ($isPublished ? ' active' : '') . '" title="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8')
                . '">' . $iconHtml . '</a>';
        }

        return '<span class="tbody-icon jgrid" title="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">'
            . $iconHtml . '</span>';
    }

    private static function renderBooleanStateToggle(
        bool $value,
        int $i,
        string $prefix,
        string $enableTask,
        string $disableTask,
        string $enableTitleKey,
        string $disableTitleKey
    ): string {
        $states = [
            1 => [$disableTask, '', $disableTitleKey, '', true, 'publish', 'publish'],
            0 => [$enableTask, '', $enableTitleKey, '', true, 'unpublish', 'unpublish'],
        ];

        return HTMLHelper::_('jgrid.state', $states, $value ? 1 : 0, $i, $prefix, true, true, 'cb');
    }
}
