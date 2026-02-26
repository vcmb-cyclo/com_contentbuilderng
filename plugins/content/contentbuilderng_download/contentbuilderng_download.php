<?php
/**
 * @version     6.0
 * @package     ContentBuilder NG Download
 * @copyright   Copyright © 2026 by XDA+GIL 
 * @license     Released under the terms of the GNU General Public License
 **/

/** ensure this file is being included by a parent file */
\defined('_JEXEC') or die('Direct Access to this location is not allowed.');

use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filter\OutputFilter;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use CB\Component\Contentbuilderng\Administrator\Helper\ContentbuilderLegacyHelper;

class plgContentContentbuilderng_download extends CMSPlugin implements SubscriberInterface
{
    /**
     * Application object.
     *
     * @var    \Joomla\CMS\Application\CMSApplication
     * @since  5.0.0
     */
    protected $app;
    
    /**
     * Database object.
     *
     * @var    \Joomla\Database\DatabaseDriver
     * @since  5.0.0
     */
    protected $db;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'onContentPrepare'];
    }

    function mime_content_type($filename)
    {

        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $ext = strtolower(array_pop(explode('.', $filename)));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        } elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        } else {
            return 'application/octet-stream';
        }
    }

	function onContentPrepare($context = '', $article = null, $params = null, $limitstart = 0, $is_list = false, $form = null, $item = null)
    {

         if ($context instanceof \Joomla\Event\EventInterface) {
            $event = $context;
            $context = (string) ($event->getArgument('context') ?? '');
            $article = $event->getArgument('subject') ?? $event->getArgument('article') ?? $event->getArgument('item');
            $params = $event->getArgument('params') ?? $params;
            $limitstart = (int) ($event->getArgument('page') ?? $event->getArgument('limitstart') ?? $limitstart);
        }
        
        $protect = false;

        $plugin = PluginHelper::getPlugin('content', 'contentbuilderng_download');
    //    $params = new Registry;
    //    $pluginParams = $params->loadString($plugin->params);


        if (!file_exists(JPATH_SITE .'/administrator/components/com_contentbuilderng/src/contentbuilderng.php')) {
            return true;
        }

        $lang = $this->app->getLanguage();
        $lang->load('plg_content_contentbuilderng_download', JPATH_ADMINISTRATOR);

        /*
         * As of Joomla! 1.6 there is just the text passed if the article data is not passed in article context.
         * (for instance with categories).
         * But we need the article id, so we use the article id flag from content generation.
         */
        if (is_object($article) && !isset($article->id) && !isset($article->cbrecord) && isset($article->text) && $article->text) {
            preg_match_all("/<!--\(cbArticleId:(\d{1,})\)-->/si", $article->text, $matched_id);
            if (isset($matched_id[1]) && isset($matched_id[1][0])) {
                $article->id = intval($matched_id[1][0]);
            }
        }

        // if this content plugin has been called from within list context
        if ($is_list) {

            if (!trim($article->text)) {
                return true;
            }

            $article->cbrecord = $form;
            $article->cbrecord->items = array();
            $article->cbrecord->items[0] = $item;
            $article->cbrecord->record_id = $item->colRecord;

        }

        if (!is_dir(JPATH_SITE .'/media/contentbuilderng')) {
            Folder::create(JPATH_SITE .'/media/contentbuilderng');
        }

        if (!file_exists(JPATH_SITE .'/media/contentbuilderng/index.html'))
            File::write(JPATH_SITE .'/media/contentbuilderng/index.html', $def = '');

        if (!is_dir(JPATH_SITE .'/media/contentbuilderng/plugins')) {
            Folder::create(JPATH_SITE .'/media/contentbuilderng/plugins');
        }

        if (!file_exists(JPATH_SITE .'/media/contentbuilderng/plugins/index.html'))
            File::write(JPATH_SITE .'/media/contentbuilderng/plugins/index.html', $def = '');

        if (!is_dir(JPATH_SITE .'/media/contentbuilderng/plugins/download')) {
            Folder::create(JPATH_SITE .'/media/contentbuilderng/plugins/download');
        }

        if (!file_exists(JPATH_SITE .'/media/contentbuilderng/plugins/download/index.html'))
            File::write(JPATH_SITE .'/media/contentbuilderng/plugins/image_scale/index.html', $def = '');

        if (isset($article->id) || isset($article->cbrecord)) {

            $matches = array();

            preg_match_all("/\{CBDownload([^}]*)\}/i", $article->text, $matches);

            if (isset($matches[0]) && is_array($matches[0]) && isset($matches[1]) && is_array($matches[1])) {

                $record = null;
                $default_title = '';
                $protect = 0;
                $form_id = 0;
                $record_id = 0;
                $type = '';

                $frontend = true;
                if ($this->app->isClient('administrator')) {
                    $frontend = false;
                }

                if (isset($article->id) && $article->id && !isset($article->cbrecord)) {

                    // try to obtain the record id if if this is just an article
                    $this->db->setQuery("Select form.`title_field`,form.`protect_upload_directory`,form.`reference_id`,article.`record_id`,article.`form_id`,form.`type`,form.`published_only`,form.`own_only`,form.`own_only_fe` From #__contentbuilderng_articles As article, #__contentbuilderng_forms As form Where form.`published` = 1 And form.id = article.`form_id` And article.`article_id` = " . $this->db->quote($article->id));
                    $data = $this->db->loadAssoc();

                    require_once(JPATH_SITE .'/administrator/components/com_contentbuilderng/src/contentbuilderng.php');
                    $form = ContentbuilderLegacyHelper::getForm($data['type'], $data['reference_id']);
                    if (!$form || !$form->exists) {
                        return true;
                    }

                    if ($form) {

                        $protect = $data['protect_upload_directory'];
                        $record = $form->getRecord($data['record_id'], $data['published_only'], $frontend ? ($data['own_only_fe'] ? (int) ($this->app->getIdentity()->id ?? 0) : -1) : ($data['own_only'] ? (int) ($this->app->getIdentity()->id ?? 0) : -1), true);
                        $default_title = $data['title_field'];
                        $form_id = $data['form_id'];
                        $record_id = $data['record_id'];
                        $type = $data['type'];
                    }

                } else if (isset($article->cbrecord) && isset($article->cbrecord->id) && $article->cbrecord->id) {

                    $protect = $article->cbrecord->protect_upload_directory;
                    $record = $article->cbrecord->items;
                    $default_title = $article->cbrecord->title_field;
                    $form_id = $article->cbrecord->id;
                    $record_id = $article->cbrecord->record_id;
                    $type = $article->cbrecord->type;

                }

                if (!$is_list) {

                    ContentbuilderLegacyHelper::setPermissions($form_id, $record_id, $frontend ? '_fe' : '');

                    if ($frontend) {
                        if (!ContentbuilderLegacyHelper::authorizeFe('view')) {
                            if (Factory::getApplication()->input->get('contentbuilderng_download_file', '', 'GET', 'STRING', CBREQUEST_ALLOWRAW, 'string')) {
                                ob_end_clean();
                                die('No Access');
                            } else {
                                return true;
                            }
                        }
                    } else {
                        if (!ContentbuilderLegacyHelper::authorize('view')) {
                            if (Factory::getApplication()->input->get('contentbuilderng_download_file', '', 'GET', 'STRING', CBREQUEST_ALLOWRAW, 'string')) {
                                ob_end_clean();
                                die('No Access');
                            } else {
                                return true;
                            }
                        }
                    }
                }

                if (!trim($default_title)) {
                    $default_title = strtotime('now');
                }

                $i = 0;
                foreach ($matches[1] as $match) {

                    $out = '';
                    $field = $is_list ? $article->cbrecord->items[0]->recName : '';
                    $box_style = 'border-width:thin::border-color:#000000::border-style:dashed::padding:5px::';
                    $info_style = '';
                    $align = '';
                    $info = true;
                    $hide_filename = false;
                    $hide_mime = false;
                    $hide_size = false;
                    $hide_downloads = false;

                    $options = explode(';', trim($match));
                    foreach ($options as $option) {
                        $keyval = explode(':', trim($option), 2);
                        if (count($keyval) == 2) {

                            $value = trim($keyval[1]);
                            switch (strtolower(trim($keyval[0]))) {
                                case 'field':
                                    $field = $value;
                                    break;
                                case 'info-style':
                                    $info_style = $value;
                                    break;
                                case 'box-style':
                                    $box_style = $value;
                                    break;
                                case 'align':
                                    $align = $value;
                                    break;
                                case 'info':
                                    $info = $value == 'true' ? true : false;
                                    break;
                                case 'hide-filename':
                                    $hide_filename = $value == 'true' ? true : false;
                                    break;
                                case 'hide-mime':
                                    $hide_mime = $value == 'true' ? true : false;
                                    break;
                                case 'hide-size':
                                    $hide_size = $value == 'true' ? true : false;
                                    break;
                                case 'hide-downloads':
                                    $hide_downloads = $value == 'true' ? true : false;
                                    break;
                            }
                        }
                    }

                    $is_series = false;

                    if ($field && isset($record) && $record !== null && is_array($record)) {

                        foreach ($record as $item) {
                            if ($default_title == $item->recElementId) {
                                $default_title = $item->recValue;
                                break;
                            }
                        }

                        foreach ($record as $item) {

                            if ($item->recName == $field) {

                                $the_files = explode("\n", str_replace("\r", '', $item->recValue));

                                $the_files_size = count($the_files);

                                if ($the_files_size > 0) {
                                    $is_series = true;
                                }

                                for ($fcnt = 0; $fcnt < $the_files_size; $fcnt++) {

                                    $the_value = str_replace(array('{CBSite}', '{cbsite}'), JPATH_SITE, trim($the_files[$fcnt]));

                                    if ($the_value) {

                                        $exists = file_exists($the_value);

                                        if ($exists) {

                                            $phpversion = explode('-', phpversion());
                                            $phpversion = $phpversion[0];
                                            // because of mime_content_type deprecation
                                            if (version_compare($phpversion, '5.3', '<')) {
                                                if (function_exists('mime_content_type')) {
                                                    $mime = mime_content_type($the_value);
                                                } else {
                                                    // fallback if not even that one exists
                                                    $mime = $this->mime_content_type($the_value);
                                                }
                                            } else {
                                                if (function_exists('finfo_open')) {
                                                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                                    $mime = finfo_file($finfo, $the_value);
                                                    finfo_close($finfo);
                                                } else {
                                                    $mime = $this->mime_content_type($the_value);
                                                }
                                            }

                                            if (Factory::getApplication()->input->get('contentbuilderng_download_file', '', 'GET', 'STRING', CBREQUEST_ALLOWRAW, 'string') == sha1($field . $the_value)) {

                                                $download_name = basename(OutputFilter::stringURLSafe($default_title) . '_' . $the_value);
                                                $file_id = md5($type . $item->recElementId . $the_value);

                                                if (!$this->app->getSession()->get('downloaded' . $type . $item->recElementId . $file_id, false, 'com_contentbuilderng.plugin.download')) {

                                                    $this->db->setQuery("Select hits From #__contentbuilderng_resource_access Where `type` = " . $this->db->Quote($type) . " And resource_id = '" . $file_id . "' And element_id = " . $this->db->Quote($item->recElementId));
                                                    if ($this->db->loadResult() === null) {
                                                        $this->db->setQuery("Insert Into #__contentbuilderng_resource_access (`type`, form_id, element_id, resource_id, hits) values (" . $this->db->Quote($type) . "," . intval($form_id) . ", " . $this->db->Quote($item->recElementId) . ", '" . $file_id . "',1)");
                                                    } else {
                                                        $this->db->setQuery("Update #__contentbuilderng_resource_access Set `type` = " . $this->db->Quote($type) . ", resource_id = '" . $file_id . "', form_id = " . intval($form_id) . ", element_id = " . $this->db->Quote($item->recElementId) . ", hits = hits + 1 Where `type` = " . $this->db->Quote($type) . " And resource_id = '" . $file_id . "' And element_id = " . $this->db->Quote($item->recElementId));
                                                    }
                                                    $this->db->execute();
                                                }

                                                $this->app->getSession()->set('downloaded' . $type . $item->recElementId . $file_id, true, 'com_contentbuilderng.plugin.download');

                                                // clean up before displaying
                                                @ob_end_clean();

                                                header('Content-Type: application/octet-stream; name="' . $download_name . '"');
                                                header('Content-Disposition: inline; filename="' . $download_name . '"');
                                                header('Content-Length: ' . @filesize($the_value));

                                                // NOTE: if running IIS and CGI, raise the CGI timeout to serve large files
                                                @$this->readfile_chunked($the_value);

                                                exit;
                                            }

                                            $info_style_ = $info_style;
                                            $box_style_ = $box_style;
                                            $info_ = $info;
                                            $align_ = $align;

                                            $download_name = basename(OutputFilter::stringURLSafe($default_title) . '_' . $the_value);
                                            $file_id = md5($type . $item->recElementId . $the_value);

                                            $this->db->setQuery("Select hits From #__contentbuilderng_resource_access Where resource_id = '" . $file_id . "' And `type` = " . intval($type) . " And element_id = " . $this->db->Quote($item->recElementId));
                                            $hits = $this->db->loadResult();

                                            if (!$hits) {
                                                $hits = 0;
                                            }

                                            $size = @number_format(filesize($the_value) / (1024 * 1024), 2) . ' MB';
                                            if (!floatval($size)) {
                                                $size = @number_format(filesize($the_value) / (1024), 2) . ' kb';
                                            }

                                            $hide_filename_ = $hide_filename;
                                            $hide_mime_ = $hide_mime;
                                            $hide_size_ = $hide_size;
                                            $hide_downloads_ = $hide_downloads;

                                            $url = Uri::getInstance()->toString();
                                            //fixing downloads on other pages than page 1
                                            if (Factory::getApplication()->input->get('controller', '', 'string') == 'list') {
                                                $url = Uri::getInstance()->base() . 'index.php?option=com_contentbuilderng&amp;task=list.display&amp;id=' . intval($form_id) . '&amp;limitstart=' . Factory::getApplication()->input->getInt('limitstart', 0);
                                            }

                                            $open_ = Route::_($url . (strstr($url, '?') !== false ? '&' : '?') . 'contentbuilderng_download_file=' . sha1($field . $the_value));

                                            $out .= '<div style="' . ($align_ ? 'float: ' . $align_ . ';' : '') . str_replace('::', ';', $box_style_) . '">
                                                        <a href="' . $open_ . '">' . Text::_('COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_DOWNLOAD') . '</a>'
                                                . ($info_ ?
                                                    '<div style="' . (str_replace('::', ';', $info_style_)) . '">
                                                                    ' . ($hide_filename_ ? '' : '<span class="cbPluginDownloadFilename">' . Text::_('COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_FILENAME') . ':</span> ' . $download_name . '<br/>') . '
                                                                    ' . ($hide_mime_ ? '' : '<span class="cbPluginDownloadMime">' . Text::_('COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_MIME') . ':</span> ' . $mime . '<br/>') . '
                                                                    ' . ($hide_size_ ? '' : '<span ' . ($hide_size_ ? ' style="display:none;" ' : '') . 'class="cbPluginDownloadSize">' . Text::_('COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_SIZE') . ':</span> ' . $size . '<br/>') . '
                                                                    ' . ($hide_downloads_ ? '' : '<span ' . ($hide_downloads_ ? ' style="display:none;" ' : '') . 'class="cbPluginDownloadDownloads">' . Text::_('COM_CONTENTBUILDERNG_PLUGIN_DOWNLOAD_DOWNLOADS') . ':</span> ' . $hits . '<br/>') . '
                                                                 </div>' : '') . '</div>';

                                            if ($is_series && $align_ && (strtolower($align_) == 'left' || strtolower($align_) == 'right')) {
                                                $out .= '<div style="float:' . strtolower($align_) . ';width: 5px;">&nbsp;</div>';
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($is_series && $align && (strtolower($align) == 'left' || strtolower($align) == 'right')) {
                        $out .= '<div style="clear:' . strtolower($align) . ';"></div>';
                    }

                    $article->text = str_replace($matches[0][$i], $out, $article->text);

                    $i++;
                }
            }
        }

        return true;
    }

    function readfile_chunked($filename)
    {
        $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
        $buffer = '';
        $handle = @fopen($filename, 'rb');
        if ($handle === false) {
            return false;
        }
        while (!@feof($handle)) {
            $buffer = @fread($handle, $chunksize);
            print $buffer;
        }
        return @fclose($handle);
    }
}
