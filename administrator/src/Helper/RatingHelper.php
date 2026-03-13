<?php
/**
 * @package     ContentBuilder NG
 * @author      Markus Bopp / XDA+GIL
 * @link        https://breezingforms-ng.vcmb.fr
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @copyright   Copyright © 2026 by XDA+GIL
 */

namespace CB\Component\Contentbuilderng\Administrator\Helper;

// No direct access
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

final class RatingHelper
{
    public static function getRating($form_id, $record_id, $colRating, $rating_slots, $lang, $rating_allowed, $rating_count, $rating_sum)
    {
        static $cssLoaded;
        /** @var \Joomla\CMS\Application\CMSWebApplicationInterface $app */
        $app = Factory::getApplication();

        if (!$cssLoaded) {
            $app->getDocument()->getWebAssetManager()->addInlineStyle('.cbVotingDisplay, .cbVotingStarButtonWrapper {
	height: 20px;
	width: 100px;
}

.cbVotingStarButtonWrapper {
	position: absolute;
	z-index: 100;	
}

.cbVotingDisplay {
	background-image: url(' . Uri::root(true) . '/components/com_contentbuilderng/assets/images/bg_votingStarOff.png);
	background-repeat: repeat-x;
        height: auto;
}

.cbVotingStars {
	position: relative;
	float: left;
	height: 20px;
	overflow: hidden;
	background-image: url(' . Uri::root(true) . '/components/com_contentbuilderng/assets/images/bg_votingStarOn.png);
	background-repeat: repeat-x;
}

.cbVotingStarButton {
	display: inline;
	height: 20px;
	width: 20px;
	float: left;
	cursor: pointer;
}

.cbRating{ width: 30px; }
.cbRatingUpDown{text-align: center; width: 90px; }
.cbRatingImage {margin: auto; display: block;}
.cbRatingCount {text-align: center; font-size: 11px;}
.cbRatingVotes {text-align: center; font-size: 11px;} 

.cbRatingImage2{
    width: 30px;
    height: 30px;
    background-image: url(' . Uri::root(true) . '/components/com_contentbuilderng/assets/images/thumbs_down.png);
    background-repeat: no-repeat;
}

.cbRatingImage{ 
    width: 30px;
    height: 30px;
    background-image: url(' . Uri::root(true) . '/components/com_contentbuilderng/assets/images/thumbs_up.png);
    background-repeat: no-repeat;
}');

            $cssLoaded = true;
        }

        ob_start();
        if ($rating_count) {
            $percentage2 = round(($colRating / 5) * 100, 2);
            $percentage3 = 100 - $percentage2;
        } else {
            $percentage2 = 0;
            $percentage3 = 0;
        }
        $percentage = round(($colRating / $rating_slots) * ($rating_slots * 20));
        if ($rating_slots > 2) {
            ?>
            <div class="cbVotingDisplay" style="width: <?php echo ($rating_slots * 20); ?>px;">
                <div class="cbVotingStarButtonWrapper">
                    <?php
        }
        $rating_link = '';
        if ($rating_allowed) {
            if ($app->isClient('site')) {
                $rating_link = Uri::root(true) . ($app->isClient('administrator') ? '/administrator' : ($app->input->getCmd('lang', '') &&
                (bool) $app->get('sef') && (bool) $app->get('sef_rewrite') ? '/' . $app->input->getCmd('lang', '') : '')) . '/?option=com_contentbuilderng&lang=' . $lang . '&task=api.display&format=json&action=rating&id=' . $form_id . '&record_id=' . $record_id;
            } else {
                $rating_link = 'index.php?option=com_contentbuilderng&lang=' . $lang . '&task=api.display&format=json&action=rating&id=' . $form_id . '&record_id=' . $record_id;
            }
        }
        for ($x = 1; $x <= $rating_slots; $x++) {
            if ($rating_link) {
                if ($rating_slots > 2) {
                    ?>
                            <div onmouseout="document.getElementById('cbVotingStars<?php echo $record_id; ?>').style.width=<?php echo $percentage; ?>+'px';"
                                onmouseover="document.getElementById('cbVotingStars<?php echo $record_id; ?>').style.width=(<?php echo $x; ?>*20)+'px';"
                                class="cbVotingStarButton" id="cbVotingStarButton_<?php echo $x; ?>"
                                onclick="cbRate('<?php echo $rating_link . '&rate=' . $x; ?>','cbRatingMsg<?php echo $record_id; ?>');">
                            </div>
                            <?php
                } else if ($rating_slots == 2) {
                    ?>
                                <div class="cbRatingUpDown">
                                    <div style="float: left;">
                                        <div class="cbRatingImage" style="cursor:pointer;"
                                            onclick="cbRate('<?php echo $rating_link . '&rate=5'; ?>','cbRatingMsg<?php echo $record_id; ?>');">
                                        </div>
                                        <div align="center" class="cbRatingCount">
                                        <?php echo $percentage2 ? $percentage2 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="float: right;">
                                        <div class="cbRatingImage2" style="cursor:pointer;"
                                            onclick="cbRate('<?php echo $rating_link . '&rate=1'; ?>','cbRatingMsg<?php echo $record_id; ?>');">
                                        </div>
                                        <div align="center" class="cbRatingCount">
                                        <?php echo $percentage3 ? $percentage3 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="clear: both;"></div>
                                    <div align="center" class="cbRatingVotes">
                                    <?php echo $rating_count == 1 ? $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                                    </div>
                                </div>
                                <?php
                                break;
                } else {
                    ?>
                                <div class="cbRating">
                                    <div class="cbRatingImage" style="cursor:pointer;"
                                        onclick="cbRate('<?php echo $rating_link . '&rate=' . $x; ?>','cbRatingMsg<?php echo $record_id; ?>');">
                                    </div>
                                    <div align="center" id="cbRatingMsg<?php echo $record_id; ?>Counter" class="cbRatingCount">
                                    <?php echo $rating_count; ?>
                                    </div>
                                    <div align="center" class="cbRatingVotes">
                                    <?php echo $rating_count == 1 ? Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                                    </div>
                                </div>
                            <?php
                }
            } else {
                if ($rating_slots > 2) {
                    ?>
                            <div class="cbVotingStarButton" style="cursor:default;" id="cbVotingStarButton_<?php echo $x; ?>"></div>
                            <?php
                } else if ($rating_slots == 2) {
                    ?>
                                <div class="cbRatingUpDown">
                                    <div style="float: left;">
                                        <div class="cbRatingImage" style="cursor:default;"></div>
                                        <div align="center" class="cbRatingCount">
                                        <?php echo $percentage2 ? $percentage2 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="float: right;">
                                        <div class="cbRatingImage2" style="cursor:default;"></div>
                                        <div align="center" class="cbRatingCount">
                                        <?php echo $percentage3 ? $percentage3 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="clear: both;"></div>
                                    <div align="center" class="cbRatingVotes">
                                    <?php echo $rating_count == 1 ? $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                                    </div>
                                </div>
                                <?php
                                break;
                } else {
                    ?>
                                <div class="cbRating">
                                    <div class="cbRatingImage" style="cursor:default;"></div>
                                    <div align="center" class="cbRatingCount">
                                    <?php echo $rating_count; ?>
                                    </div>
                                    <div align="center" class="cbRatingVotes">
                                    <?php echo $rating_count == 1 ? Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                                    </div>
                                </div>
                            <?php
                }
            }
        }
        if ($rating_slots > 2) {
            ?>
                </div>
                <div class="cbVotingStars" id="cbVotingStars<?php echo $record_id; ?>" style="width: <?php echo $percentage; ?>px;">
                </div>
                <div style="clear: left;"></div>
                <div align="center" class="cbRatingVotes">
                    <?php echo $rating_count == 1 ? $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                </div>
            </div>
            <?php
        }
        ?>
        <div style="display:none;" class="cbRatingMsg" id="cbRatingMsg<?php echo $record_id; ?>"></div>
        <?php
        $c = ob_get_contents();
        ob_end_clean();

        return $c;
    }
}
