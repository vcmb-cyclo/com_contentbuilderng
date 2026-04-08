<?php
/**
 * ContentBuilder NG Form table.
 *
 * Table description.
 *
 * @package     ContentBuilder NG
 * @subpackage  Administrator.Tables
 * @author      Markus Bopp / XDA+GIL
 * @copyright   Copyright © 2024–2026 by XDA+GIL
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @link        https://breezingforms-ng.vcmb.fr
 * @since       6.0.0  Joomla 6 rewrite.
 */

namespace CB\Component\Contentbuilderng\Administrator\Table;

// No direct access
\defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class FormTable extends Table
{
    /**
     * Primary Key
     *
     * @var int
     */
    public $id = 0;
    
    public $type = '';
    public $reference_id = 0;
    public $name = '';
    public $title = '';
    public $tag = '';
    public $created = null;
    public $modified = null;
    public $created_by = 0;
    public $details_template = '';
    public $details_prepare = '';
    public $editable_template = '';
    public $editable_prepare = '';

    public $email_template = '';
    public $email_subject = '';
    public $email_alternative_from = '';
    public $email_alternative_fromname = '';
    public $email_recipients = '';
    public $email_recipients_attach_uploads = '';
    public $email_html = '';

    public $email_admin_template = '';
    public $email_admin_subject = '';
    public $email_admin_alternative_from = '';
    public $email_admin_alternative_fromname = '';
    public $email_admin_recipients = '';
    public $email_admin_recipients_attach_uploads = '';
    public $email_admin_html = '';

    public $modified_by = 0;
    public $print_button = 1;
    public $metadata = 1;
    public $export_xls = 0;
    public $edit_button = 0;
    public $new_button = 0;
    public $list_state = 0;
    public $list_publish = 0;
    public $list_rating = 0;
    public $select_column = 0;
    public $show_id_column = 0;
    public $use_view_name_as_title = 0;
    public $intro_text = '';
    public $published_only = 0;
    public $display_in = 0;
    public $ordering = 0;
    public $own_only = 0;
    public $own_only_fe = 0;
    public $published = 0;
    public $config = '';
    public $initial_sort_order = -1;
    public $initial_sort_order2 = -1;
    public $initial_sort_order3 = -1;
    public $initial_order_dir = 'desc';
    public $create_articles = 0;
    public $default_section = 0;
    public $default_category = 0;
    public $title_field = 0;
    public $delete_articles = 1;
    public $edit_by_type = 0;
    public $email_notifications = 1;
    public $email_update_notifications = 0;
    public $limited_article_options = 1;
    public $limited_article_options_fe = 1;
    public $upload_directory = 'media/com_contentbuilderng/upload';
    public $protect_upload_directory = 1;
    public $last_update = null;
    public $limit_add = 0;
    public $limit_edit = 0;
    public $verification_required_view = 0;
    public $verification_days_view = 0;
    public $verification_required_new = 0;
    public $verification_days_new = 0;
    public $verification_required_edit = 0;
    public $verification_days_edit = 0;
    public $verification_url_view = '';
    public $verification_url_new = '';
    public $verification_url_edit = '';
    public $default_lang_code = '*';
    public $default_lang_code_ignore = 0;
    public $show_all_languages_fe = 1;
    public $list_language = 0;
    public $default_publish_up_days = 0;
    public $default_publish_down_days = 0;
    public $default_access = 0;
    public $default_featured = 0;
    public $list_article = 0;
    public $list_author = 0;
    public $list_last_modification = 0;
    public $cb_show_author = 1;
    public $cb_show_top_bar = 1;
    public $cb_show_bottom_bar = 0;
    public $cb_show_details_top_bar = 1;
    public $cb_show_details_bottom_bar = 0;
    public $show_back_button = 1;
    public $cb_filter_in_title = 0;
    public $cb_prefix_in_title = 0;

    public $act_as_registration = 0;
    public $registration_username_field = '';
    public $registration_password_field = '';
    public $registration_password_repeat_field = '';
    public $registration_email_field = '';
    public $registration_email_repeat_field = '';
    public $registration_name_field = '';

    public $auto_publish = 0;

    public $force_login = 0;
    public $force_url = '';

    public $registration_bypass_plugin = '';
    public $registration_bypass_plugin_params = '';
    public $registration_bypass_verification_name = '';
    public $registration_bypass_verify_view = '';

    public $theme_plugin = '';

    public $rating_slots = 5;

    public $rand_date_update = null;
    public $rand_update = '86400';

    public $article_record_impact_publish = 0;
    public $article_record_impact_language = 0;

    public $allow_external_filter = 0;

    public $show_filter = 1;

    public $show_records_per_page = 1;

    public $button_bar_sticky = 0;

    public $list_header_sticky = 0;

    public $show_preview_link = 0;

    public $initial_list_limit = 25;

    public $save_button_title = '';

    public $apply_button_title = '';

    public $filter_exact_match = 0;

    /**
     * Constructor
     *
     * @param object Database connector object
     */
    function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__contentbuilderng_forms', 'id', $db);

        // Joomla attend un champ "state" pour publish/unpublish au lieu de "published"
        $this->setColumnAlias('state', 'published');
    }
}
