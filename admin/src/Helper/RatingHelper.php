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
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

final class RatingHelper
{
    public static function getRating($form_id, $record_id, $colRating, $rating_slots, $lang, $rating_allowed, $rating_count, $rating_sum)
    {
        static $cssLoaded;
        static $scriptRendered;
        /** @var \Joomla\CMS\Application\CMSWebApplicationInterface $app */
        $app = Factory::getApplication();

        if (!$cssLoaded) {
            $mediaRoot = Uri::root(true) . '/media/com_contentbuilderng/images';
            $app->getDocument()->getWebAssetManager()->addInlineStyle('.cbVotingDisplay, .cbVotingStarButtonWrapper {
	height: 20px;
	width: 100px;
}

.cbVotingStarButtonWrapper {
	position: absolute;
	z-index: 100;
	top: 0;
	left: 0;
}

.cbVotingDisplay {
	position: relative;
	background-image: url(' . $mediaRoot . '/bg_votingStarOff.png);
	background-repeat: repeat-x;
        min-height: 20px;
        display: inline-block;
}

.cbVotingStars {
	position: relative;
	float: left;
	height: 20px;
	overflow: hidden;
	background-image: url(' . $mediaRoot . '/bg_votingStarOn.png);
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
    background-image: url(' . $mediaRoot . '/thumbs_down.png);
    background-repeat: no-repeat;
}

.cbRatingImage{ 
    width: 30px;
    height: 30px;
    background-image: url(' . $mediaRoot . '/thumbs_up.png);
    background-repeat: no-repeat;
}');

            $cssLoaded = true;
        }

        ob_start();
        if ($rating_allowed && !$scriptRendered) {
            $scriptRendered = true;
            $csrfToken = Session::getFormToken();
            $voteSingular = Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR');
            $votePlural = Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL');
            ?>
            <script>
            (function(){
                var cbLastId = null;
                var cbVoteLabels = {
                    singular: <?php echo json_encode($voteSingular); ?>,
                    plural: <?php echo json_encode($votePlural); ?>
                };
                function cbFadeOut(el){
                    if(!el){return;}
                    window.setTimeout(function(){ el.style.display = "none"; }, 1800);
                }
                function cbRetrieveRatingResults(payload, lastId){
                    var result = payload;
                    if(result && typeof result.success !== "undefined"){
                        result = result.success ? (result.data || {}) : {code:1,msg:result.message || ""};
                    }
                    var box = document.getElementById(lastId);
                    if(!box || !result){
                        cbLastId = null;
                        return;
                    }
                    box.style.display = "block";
                    box.textContent = result.msg || "";
                    if(result.code === 0){
                        var counter = document.getElementById(lastId + "Counter");
                        if(counter && !isNaN(Number(counter.textContent))){
                            counter.textContent = String(Number(counter.textContent) + 1);
                        }
                    }
                    cbFadeOut(box);
                    cbLastId = null;
                }
                window.cbRetrieveRatingResults = cbRetrieveRatingResults;
                window.cbRate = function(url, lastId){
                    if(cbLastId !== null){
                        return false;
                    }
                    cbLastId = lastId;
                    var tokenParam = <?php echo json_encode($csrfToken . '=1'); ?>;
                    var separator = url.indexOf("?") === -1 ? "?" : "&";
                    var requestUrl = url + separator + tokenParam;
                    var urlObject = null;
                    var clickedRate = 0;
                    var recordId = "";

                    try {
                        urlObject = new URL(url, window.location.href);
                        clickedRate = Number(urlObject.searchParams.get("rate") || 0);
                        recordId = String(urlObject.searchParams.get("record_id") || "");
                    } catch (error) {}

                    fetch(requestUrl, {
                        method: "POST",
                        credentials: "same-origin",
                        headers: {
                            "X-Requested-With": "XMLHttpRequest",
                            "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
                        },
                        body: tokenParam
                    })
                    .then(function(response){
                        return response.text().then(function(text){
                            var payload = null;
                            try {
                                payload = JSON.parse(text);
                            } catch (error) {
                                payload = {success:false,message:text || "Rating error"};
                            }
                            if(!response.ok){
                                throw payload;
                            }
                            if (clickedRate > 0 && recordId !== "") {
                                var stars = document.getElementById("cbVotingStars" + recordId);
                                var starWidth = String(clickedRate * 20) + "px";
                                if (stars) {
                                    stars.style.width = starWidth;
                                }

                                document.querySelectorAll('[id^="cbVotingStarButton_"]').forEach(function(el) {
                                    if (el && typeof el.getAttribute === "function" && String(el.getAttribute("onclick") || "").indexOf("cbRatingMsg" + recordId) !== -1) {
                                        el.setAttribute("onmouseout", 'document.getElementById("cbVotingStars' + recordId + '").style.width=' + JSON.stringify(starWidth) + ';');
                                    }
                                });

                                var votingWrapper = stars ? stars.parentElement : null;
                                if (votingWrapper) {
                                    votingWrapper.querySelectorAll(".cbVotingStarButton").forEach(function(el) {
                                        el.setAttribute("onmouseout", 'document.getElementById("cbVotingStars' + recordId + '").style.width=' + JSON.stringify(starWidth) + ';');
                                    });
                                }

                                var counter = document.getElementById(lastId + "Counter");
                                var voteCount = counter && !isNaN(Number(counter.textContent))
                                    ? Number(counter.textContent)
                                    : 0;
                                voteCount += 1;

                                if (counter) {
                                    counter.textContent = String(voteCount);
                                }

                                var votesContainers = document.querySelectorAll("#cbVotingStars" + recordId + ", #" + lastId + "Counter");
                                var scope = null;
                                if (votesContainers.length > 0) {
                                    scope = votesContainers[0].closest(".cbVotingDisplay, .cbRating, .cbRatingUpDown");
                                }
                                if (!scope && counter) {
                                    scope = counter.closest(".cbVotingDisplay, .cbRating, .cbRatingUpDown");
                                }
                                if (scope) {
                                    scope.querySelectorAll(".cbRatingVotes").forEach(function(el) {
                                        el.textContent = voteCount + " " + (voteCount === 1 ? cbVoteLabels.singular : cbVoteLabels.plural);
                                    });
                                }
                            }
                            cbRetrieveRatingResults(payload, lastId);
                        });
                    })
                    .catch(function(error){
                        cbRetrieveRatingResults(error && typeof error === "object" ? error : {success:false,message:"Rating error"}, lastId);
                    });
                    return false;
                };
            })();
            </script>
            <?php
        }
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
                $rating_link = Uri::root(true) . ($app->isClient('administrator') ? '/administrator' : ($app->getInput()->getCmd('lang', '') &&
                (bool) $app->get('sef') && (bool) $app->get('sef_rewrite') ? '/' . $app->getInput()->getCmd('lang', '') : '')) . '/?option=com_contentbuilderng&lang=' . $lang . '&task=api.display&format=json&action=rating&id=' . $form_id . '&record_id=' . $record_id;
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
                                        <div class="cbRatingCount text-center">
                                        <?php echo $percentage2 ? $percentage2 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="float: right;">
                                        <div class="cbRatingImage2" style="cursor:pointer;"
                                            onclick="cbRate('<?php echo $rating_link . '&rate=1'; ?>','cbRatingMsg<?php echo $record_id; ?>');">
                                        </div>
                                        <div class="cbRatingCount text-center">
                                        <?php echo $percentage3 ? $percentage3 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="clear: both;"></div>
                                    <div class="cbRatingVotes text-center">
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
                                    <div id="cbRatingMsg<?php echo $record_id; ?>Counter" class="cbRatingCount text-center">
                                    <?php echo $rating_count; ?>
                                    </div>
                                    <div class="cbRatingVotes text-center">
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
                                        <div class="cbRatingCount text-center">
                                        <?php echo $percentage2 ? $percentage2 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="float: right;">
                                        <div class="cbRatingImage2" style="cursor:default;"></div>
                                        <div class="cbRatingCount text-center">
                                        <?php echo $percentage3 ? $percentage3 . '%' : ''; ?>
                                        </div>
                                    </div>
                                    <div style="clear: both;"></div>
                                    <div class="cbRatingVotes text-center">
                                    <?php echo $rating_count == 1 ? $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_SINGULAR') : $rating_count . ' ' . Text::_('COM_CONTENTBUILDERNG_VOTES_PLURAL'); ?>
                                    </div>
                                </div>
                                <?php
                                break;
                } else {
                    ?>
                                <div class="cbRating">
                                    <div class="cbRatingImage" style="cursor:default;"></div>
                                    <div class="cbRatingCount text-center">
                                    <?php echo $rating_count; ?>
                                    </div>
                                    <div class="cbRatingVotes text-center">
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
                <div class="cbRatingVotes text-center">
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
