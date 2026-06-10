<?php
 /**
 * @package     ContentBuilderNG
 * @copyright Copyright © 2026 XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
*/ 
 
if (!defined('_JEXEC')) {
	define('_JEXEC', 1);
}

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Factory;

ob_start();

require_once dirname(__FILE__) . '/../../../../includes/app.php';
/* To use Joomla's Database Class */

require_once(JPATH_SITE .'/libraries/src/Factory.php');

// Instantiate the application.
$app = Factory::getApplication('administrator');

ob_end_clean();

\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

include JPATH_SITE .'/components/com_contentbuilderng/images/securimage/securimage.php';

$img = new securimage();

//Change some settings
$img->image_width = 230;
$img->image_height = 80;
$img->perturbation = 0.9;

$img->image_bg_color = new Securimage_Color("#6495ED");
$img->text_color = new Securimage_Color("#B0E0E6");
$img->line_color = new Securimage_Color("#B0E0E6");
$img->noise_color = new Securimage_Color("#B0E0E6");

$img->use_transparent_text = false;
$img->text_transparency_percentage = 60; // 100 = completely transparent
$img->num_lines = 15;
$img->image_signature = '';
$img->use_wordlist = true;

http_response_code(200);

$img->show(JPATH_SITE .'/components/com_contentbuilderng/images/securimage/backgrounds/bg6.jpg');


