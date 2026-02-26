<?php
/**
 * @version     6.0
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @copyright   Copyright © 2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
\defined('_JEXEC') or die ('Restricted access');
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Event\GenericEvent as Event;
use Joomla\Event\SubscriberInterface;


class plgContentbuilderng_verifyPassthrough extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onViewport' => 'onViewport',
            'onSetup' => 'onSetup',
            'onForward' => 'onForward',
            'onVerify' => 'onVerify',
        ];
    }

    /**
     * Will be called in the content element (article or record)
     * If the return is not empty, it will render the returned value.
     * 
     * By that things like coupon codes may be implemented.
     * 
     * @param string $link the link that points to the verifier
     * @param string $plugin_settings The raw query string with the plugin options
     * @return string empty for nothing (default) or a string to render instead of the default
     */
    public function onViewport(Event $event): string
    {
        $args = array_values($event->getArguments());
        $link = (string) ($args[0] ?? '');
        $plugin_settings = (string) ($args[1] ?? '');

        return '';
    }

    /**
     * Will always be called by the verifier
     * 
     * @param type $return_url
     * @param type $options
     * @return string empty if everything is ok, else a message describing the problem 
     */
    public function onSetup(Event $event): string
    {
        $args = array_values($event->getArguments());
        $return_url = (string) ($args[0] ?? '');
        $options = isset($args[1]) && is_array($args[1]) ? $args[1] : [];

        return '';
    }

    /**
     * Will be called on forward, right after setup IF there is no verification yet
     * 
     * @param string $return_url
     * @param array $options 
     */
    public function onForward(Event $event): string
    {
        $args = array_values($event->getArguments());
        $return_url = (string) ($args[0] ?? '');
        $options = isset($args[1]) && is_array($args[1]) ? $args[1] : [];
        return $return_url;
    }

    /**
     * Will be called on verification
     * 
     * @param string $return_url
     * @param array $options
     * @return mixed boolean false on errors or an array with optional verification data (msg[string], is_test[0/1], data [array])
     */
    public function onVerify(Event $event): array
    {
        $args = array_values($event->getArguments());
        $return_url = (string) ($args[0] ?? '');
        $options = isset($args[1]) && is_array($args[1]) ? $args[1] : [];

        return array(
            'msg' => '',
            'is_test' => 0,
            'data' => array()
        );
    }
}
