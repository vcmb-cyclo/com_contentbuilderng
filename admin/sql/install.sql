-- =============================================================================
-- ContentBuilder NG — Schema installation
-- =============================================================================

-- -----------------------------------------------------------------------------
-- #__contentbuilderng_articles
-- Links a CB record to a generated Joomla article.
--
--   id            Auto-increment primary key.
--   article_id    ID of the generated Joomla article (#__content.id).
--   type          Source form type (e.g. "com_breezingformsng").
--   reference_id  ID of the source form (BF form id).
--   record_id     ID of the CB data record.
--   form_id       ID of the CB view/form (#__contentbuilderng_forms.id).
--   last_update   Timestamp of last sync between record and article.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_articles`
(
    `id`          int      NOT NULL AUTO_INCREMENT,
    `article_id`  int      NOT NULL DEFAULT '0',
    `type`        varchar(55)  COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `reference_id` int     NOT NULL DEFAULT '0',
    `record_id`   bigint   NOT NULL DEFAULT '0',
    `form_id`     int      NOT NULL DEFAULT '0',
    `last_update` datetime NULL     DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `record_id`   (`record_id`, `form_id`),
    KEY `article_id`  (`article_id`, `record_id`),
    KEY `record_id_2` (`record_id`),
    KEY `type`        (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_elements
-- Configuration of each column/field displayed in a CB view.
-- Each row maps one source-form field to its display settings for a given view.
--
--   id                       Auto-increment primary key.
--   form_id                  Parent CB view (#__contentbuilderng_forms.id).
--   reference_id             Identifier of the field in the source form.
--   type                     Field type (text, image, date, …).
--   change_type              How the value is transformed for display.
--   options                  JSON display options for this field.
--   custom_init_script       PHP executed before the field is rendered.
--   custom_action_script     PHP executed on record action for this field.
--   custom_validation_script PHP validation logic for this field.
--   validation_message       Error message shown when validation fails.
--   default_value            Default value used when the source value is empty.
--   hint                     Placeholder/hint shown in edit mode.
--   label                    Column label shown in list and details views.
--   list_include             1 = include in list view; 0 = hidden.
--   search_include           1 = include in the search/filter bar.
--   item_wrapper             Template snippet wrapping the displayed value.
--   wordwrap                 Max character width before wrapping (0 = unlimited).
--   linkable                 1 = value is rendered as a link to the details view.
--   api_allowed              1 = field is exposed via the CB REST API.
--   editable                 1 = field is editable in the inline edit view.
--   validations              JSON array of active validation plugin names.
--   published                1 = active; 0 = hidden everywhere.
--   order_type               Sort strategy for this field (e.g. "natural", "numeric").
--   ordering                 Display position within the view (ascending).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_elements`
(
    `id`                       int      NOT NULL AUTO_INCREMENT,
    `form_id`                  int      NOT NULL DEFAULT '0',
    `reference_id`             int      NOT NULL DEFAULT '0',
    `type`                     varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `change_type`              varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `options`                  text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_init_script`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_action_script`     text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_validation_script` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `validation_message`       text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `default_value`            text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `hint`                     text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `label`                    varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `list_include`             tinyint(1) NOT NULL DEFAULT '1',
    `search_include`           tinyint(1) NOT NULL DEFAULT '0',
    `item_wrapper`             text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `wordwrap`                 int        NOT NULL DEFAULT '0',
    `linkable`                 tinyint(1) NOT NULL DEFAULT '0',
    `api_allowed`              tinyint(1) NOT NULL DEFAULT '0',
    `editable`                 tinyint(1) NOT NULL DEFAULT '0',
    `validations`              text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `published`                tinyint(1) NOT NULL DEFAULT '1',
    `order_type`               varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `ordering`                 int        NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `reference_id` (`reference_id`),
    KEY `form_id`      (`form_id`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_forms
-- Central configuration table: one row per CB view (a "form" in CB terminology).
-- Each view ties a source form to display, edit, email, and permission settings.
--
-- -- Identity --
--   id                              Auto-increment primary key.
--   type                            Source component type ("com_breezingformsng", …).
--   reference_id                    ID of the source form in the source component.
--   name                            Internal machine name (unique slug).
--   tag                             Optional grouping tag.
--   title                           Human-readable view title.
--
-- -- Templates --
--   details_template                PHP/HTML template for the record details page.
--   details_prepare                 PHP executed before details template rendering.
--   editable_template               PHP/HTML template for the inline edit form.
--   editable_prepare                PHP executed before edit template rendering.
--
-- -- Audit --
--   created / modified              Creation and last-modification timestamps.
--   created_by / modified_by        Username of creator / last editor.
--   last_update                     Timestamp of last data synchronisation.
--
-- -- List view display --
--   metadata                        1 = emit HTML meta tags on details page.
--   export_xls                      1 = show Excel export button.
--   print_button                    1 = show Print button on details page.
--   show_id_column                  1 = display the record ID column in list.
--   use_view_name_as_title          1 = use the view name as the page <title>.
--   display_in                      Where to display the list (0=frontend, 1=admin, …).
--   edit_button                     1 = show Edit button in list row.
--   new_button                      1 = show New-record button.
--   list_state                      1 = show the state column in list.
--   list_publish                    1 = show the published column in list.
--   list_language                   1 = show the language column.
--   list_article                    1 = show the linked article column.
--   list_author                     1 = show the author column.
--   list_last_modification          1 = show the last-modified column.
--   cb_show_author                  1 = show author in details view footer.
--   cb_show_top_bar                 1 = show toolbar at top of list.
--   cb_show_bottom_bar              1 = show toolbar at bottom of list.
--   cb_show_details_top_bar         1 = show toolbar at top of details page.
--   cb_show_details_bottom_bar      1 = show toolbar at bottom of details page.
--   show_back_button                1 = show Back button on details page.
--   show_title_breadcrumb           1 = render page title as breadcrumb on details/edit pages.
--   cb_filter_in_title              1 = append active filter value to page title.
--   cb_prefix_in_title              1 = prepend view name to page title.
--   select_column                   1 = show checkbox column for bulk actions.
--   show_filter                     1 = show the search/filter bar.
--   show_records_per_page           1 = show the records-per-page selector.
--   button_bar_sticky               1 = make the toolbar sticky on scroll.
--   show_preview_link               1 = show the admin preview link.
--   initial_list_limit              Default records per page (25).
--   filter_exact_match              1 = search uses exact match instead of LIKE.
--   allow_external_filter           1 = accept filter parameters from URL.
--
-- -- Sorting --
--   initial_sort_order / 2 / 3      Default sort column(s) (element id or -1=none).
--   initial_order_dir               Default sort direction ("asc" or "desc").
--
-- -- Access control --
--   published_only                  1 = only show published records in list.
--   own_only                        1 = admin list shows only own records.
--   own_only_fe                     1 = frontend list shows only own records.
--   limit_add                       Max records a user may add (0 = unlimited).
--   limit_edit                      Max records a user may edit (0 = unlimited).
--
-- -- Verification / payment gate --
--   verification_required_view/new/edit    1 = gate enabled for view/new/edit.
--   verification_days_view/new/edit        Validity window in days for the gate.
--   verification_url_view/new/edit         Redirect URL for each gate.
--
-- -- Articles integration --
--   default_section                 Legacy section id (unused in J6).
--   default_category                Default Joomla category for generated articles.
--   default_lang_code               Default language tag ("*" = all).
--   default_lang_code_ignore        1 = ignore language on article creation.
--   create_articles                 1 = auto-create a Joomla article per record.
--   delete_articles                 1 = delete the linked article when record is deleted.
--   title_field                     Element id whose value is used as article title.
--   edit_by_type                    1 = inline-edit uses source form's own edit UI.
--   auto_publish                    1 = newly created records are auto-published.
--   article_record_impact_publish   1 = publishing the record also publishes its article.
--   article_record_impact_language  1 = changing the record language updates its article.
--   limited_article_options         1 = restrict article option fields (admin).
--   limited_article_options_fe      1 = restrict article option fields (frontend).
--   default_access                  Default Joomla access level for new articles.
--   default_featured                1 = new articles are featured by default.
--   default_publish_up_days         Days until publish_up from creation (0 = immediate).
--   default_publish_down_days       Days until publish_down from creation (0 = never).
--
-- -- File uploads --
--   upload_directory                Server path for uploaded files.
--   protect_upload_directory        1 = protect uploads with a .htaccess file.
--
-- -- Email notifications (admin) --
--   email_notifications             1 = send admin email on new record.
--   email_update_notifications      1 = send admin email on record update.
--   email_admin_template            HTML/text body template for admin email.
--   email_admin_subject             Subject line for admin email.
--   email_admin_alternative_from    Override sender address for admin email.
--   email_admin_alternative_fromname Override sender name for admin email.
--   email_admin_recipients          Comma-separated extra recipients.
--   email_admin_recipients_attach_uploads  Recipients who receive file attachments.
--   email_admin_html                1 = send admin email as HTML.
--
-- -- Email notifications (submitter) --
--   email_template                  HTML/text body template for submitter email.
--   email_subject                   Subject line for submitter email.
--   email_alternative_from          Override sender address for submitter email.
--   email_alternative_fromname      Override sender name for submitter email.
--   email_recipients                Comma-separated extra submitter recipients.
--   email_recipients_attach_uploads Recipients who receive file attachments.
--   email_html                      1 = send submitter email as HTML.
--
-- -- Registration mode --
--   act_as_registration             1 = form creates a Joomla user account.
--   registration_username_field     Element reference_id mapped to username.
--   registration_password_field     Element reference_id mapped to password.
--   registration_password_repeat_field  Element reference_id mapped to password confirmation.
--   registration_name_field         Element reference_id mapped to full name.
--   registration_email_field        Element reference_id mapped to email.
--   registration_email_repeat_field Element reference_id mapped to email confirmation.
--   force_login                     1 = require login before accessing this view.
--   force_url                       Redirect URL after forced login.
--   registration_bypass_plugin      Plugin name used to bypass Joomla registration.
--   registration_bypass_plugin_params  JSON params for the bypass plugin.
--   registration_bypass_verification_name  Verification name used by bypass plugin.
--   registration_bypass_verify_view View name used for bypass verification.
--
-- -- Ratings --
--   list_rating                     1 = enable star rating on this view.
--   rating_slots                    Number of rating stars (default 5).
--
-- -- Randomisation --
--   rand_date_update                Timestamp of last random-sort refresh.
--   rand_update                     Refresh interval in seconds (default 86400).
--
-- -- UI labels --
--   save_button_title               Custom label for the Save button (empty = default).
--   apply_button_title              Custom label for the Apply button (empty = default).
--
-- -- Theme --
--   theme_plugin                    Name of the active theme plugin.
--
-- -- Intro --
--   intro_text                      HTML intro shown above the list.
--   config                          JSON packed-data blob for miscellaneous settings.
--
-- -- Publish --
--   published                       1 = view is active; 0 = disabled.
--   debug_mode                      1 = enable view-specific frontend debugging.
--   debug_show_bf_id                1 = show the BreezingForms record ID column.
--   debug_enable_logs               1 = write a CBNG entry for frontend requests.
--   debug_show_request_logs         1 = show CBNG entries from the current request.
--   debug_show_permissions          1 = show resolved frontend permissions.
--   debug_show_filters              1 = show active frontend filters.
--   debug_show_cb_id                1 = show the internal CBNG record ID.
--   ordering                        Display order in the admin forms list.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_forms`
(
    `id`                                  int          NOT NULL AUTO_INCREMENT,
    `type`                                varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `reference_id`                        int          NOT NULL DEFAULT '0',
    `name`                                varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `tag`                                 varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title`                               varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `details_template`                    longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `details_prepare`                     longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `editable_template`                   longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `editable_prepare`                    longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `created`                             datetime     NULL DEFAULT CURRENT_TIMESTAMP,
    `modified`                            datetime     NULL DEFAULT NULL,
    `created_by`                          varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `modified_by`                         varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `metadata`                            tinyint(1)   NOT NULL DEFAULT '1',
    `export_xls`                          tinyint(1)   NOT NULL DEFAULT '0',
    `print_button`                        tinyint(1)   NOT NULL DEFAULT '1',
    `show_id_column`                      tinyint(1)   NOT NULL DEFAULT '0',
    `use_view_name_as_title`              tinyint(1)   NOT NULL DEFAULT '0',
    `display_in`                          tinyint(1)   NOT NULL DEFAULT '0',
    `edit_button`                         tinyint(1)   NOT NULL DEFAULT '0',
    `new_button`                          tinyint(1)   NOT NULL DEFAULT '0',
    `list_state`                          tinyint(1)   NOT NULL DEFAULT '0',
    `list_publish`                        tinyint(1)   NOT NULL DEFAULT '0',
    `list_language`                       tinyint(1)   NOT NULL DEFAULT '0',
    `list_article`                        tinyint(1)   NOT NULL DEFAULT '0',
    `list_author`                         tinyint(1)   NOT NULL DEFAULT '0',
    `list_last_modification`              tinyint(1)   NOT NULL DEFAULT '0',
    `cb_show_author`                      tinyint(1)   NOT NULL DEFAULT '1',
    `cb_show_top_bar`                     tinyint(1)   NOT NULL DEFAULT '1',
    `cb_show_bottom_bar`                  tinyint(1)   NOT NULL DEFAULT '0',
    `cb_show_details_top_bar`             tinyint(1)   NOT NULL DEFAULT '1',
    `cb_show_details_bottom_bar`          tinyint(1)   NOT NULL DEFAULT '0',
    `show_back_button`                    tinyint(1)   NOT NULL DEFAULT '1',
    `show_title_breadcrumb`               tinyint(1)   NOT NULL DEFAULT '1',
    `cb_filter_in_title`                  tinyint(1)   NOT NULL DEFAULT '0',
    `cb_prefix_in_title`                  tinyint(1)   NOT NULL DEFAULT '0',
    `select_column`                       tinyint(1)   NOT NULL DEFAULT '0',
    `published_only`                      tinyint(1)   NOT NULL DEFAULT '0',
    `own_only`                            tinyint(1)   NOT NULL DEFAULT '0',
    `own_only_fe`                         tinyint(1)   NOT NULL DEFAULT '0',
    `ordering`                            int          NOT NULL DEFAULT '0',
    `intro_text`                          text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `config`                              longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `default_section`                     int          NOT NULL DEFAULT '0',
    `default_category`                    int          NOT NULL DEFAULT '0',
    `default_lang_code`                   varchar(7)   COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '*',
    `default_lang_code_ignore`            tinyint(1)   NOT NULL DEFAULT '0',
    `create_articles`                     tinyint(1)   NOT NULL DEFAULT '1',
    `published`                           tinyint(1)   NOT NULL DEFAULT '0',
    `debug_mode`                          tinyint(1)   NOT NULL DEFAULT '0',
    `debug_show_bf_id`                    tinyint(1)   NOT NULL DEFAULT '0',
    `debug_enable_logs`                   tinyint(1)   NOT NULL DEFAULT '0',
    `debug_show_request_logs`             tinyint(1)   NOT NULL DEFAULT '0',
    `debug_show_permissions`              tinyint(1)   NOT NULL DEFAULT '0',
    `debug_show_filters`                  tinyint(1)   NOT NULL DEFAULT '0',
    `debug_show_cb_id`                    tinyint(1)   NOT NULL DEFAULT '0',
    `initial_sort_order`                  varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_sort_order2`                 varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_sort_order3`                 varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_order_dir`                   varchar(4)   COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'desc',
    `title_field`                         int          NOT NULL DEFAULT '0',
    `delete_articles`                     tinyint(1)   NOT NULL DEFAULT '1',
    `edit_by_type`                        tinyint(1)   NOT NULL DEFAULT '0',
    `email_notifications`                 tinyint(1)   NOT NULL DEFAULT '1',
    `email_update_notifications`          tinyint(1)   NOT NULL DEFAULT '0',
    `limited_article_options`             tinyint(1)   NOT NULL DEFAULT '1',
    `limited_article_options_fe`          tinyint(1)   NOT NULL DEFAULT '1',
    `upload_directory`                    text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `protect_upload_directory`            tinyint(1)   NOT NULL DEFAULT '1',
    `last_update`                         datetime     NULL DEFAULT NULL,
    `limit_add`                           int          NOT NULL DEFAULT '0',
    `limit_edit`                          int          NOT NULL DEFAULT '0',
    `verification_required_view`          tinyint(1)   NOT NULL DEFAULT '0',
    `verification_days_view`              float        NOT NULL DEFAULT '0',
    `verification_required_new`           tinyint(1)   NOT NULL DEFAULT '0',
    `verification_days_new`               float        NOT NULL DEFAULT '0',
    `verification_required_edit`          tinyint(1)   NOT NULL DEFAULT '0',
    `verification_days_edit`              float        NOT NULL DEFAULT '0',
    `verification_url_view`               text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `verification_url_new`                text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `verification_url_edit`               text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `show_all_languages_fe`               tinyint(1)   NOT NULL DEFAULT '1',
    `default_publish_up_days`             int          NOT NULL DEFAULT '0',
    `default_publish_down_days`           int          NOT NULL DEFAULT '0',
    `default_access`                      int          NOT NULL DEFAULT '0',
    `default_featured`                    tinyint(1)   NOT NULL DEFAULT '0',
    `email_admin_template`                text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_subject`                 varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_alternative_from`        varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_alternative_fromname`    varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_recipients`              text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_recipients_attach_uploads` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_html`                    tinyint(1)   NOT NULL DEFAULT '0',
    `email_template`                      text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_subject`                       varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_alternative_from`              varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_alternative_fromname`          varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
    `email_recipients`                    text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_recipients_attach_uploads`     text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_html`                          tinyint(1)   NOT NULL DEFAULT '0',
    `act_as_registration`                 tinyint(1)   NOT NULL DEFAULT '0',
    `registration_username_field`         varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_password_field`         varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_password_repeat_field`  varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_name_field`             varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_email_field`            varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_email_repeat_field`     varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `auto_publish`                        tinyint(1)   NOT NULL DEFAULT '0',
    `force_login`                         tinyint(1)   NOT NULL DEFAULT '0',
    `force_url`                           text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `registration_bypass_plugin`          varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_bypass_plugin_params`   text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `registration_bypass_verification_name` varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_bypass_verify_view`     varchar(32)  COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `theme_plugin`                        varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `list_rating`                         tinyint(1)   NOT NULL DEFAULT '0',
    `rating_slots`                        tinyint(1)   NOT NULL DEFAULT '5',
    `rand_date_update`                    datetime     NULL DEFAULT NULL,
    `rand_update`                         int          NOT NULL DEFAULT '86400',
    `article_record_impact_publish`       tinyint(1)   NOT NULL DEFAULT '0',
    `article_record_impact_language`      tinyint(1)   NOT NULL DEFAULT '0',
    `allow_external_filter`               tinyint(1)   NOT NULL DEFAULT '0',
    `show_filter`                         tinyint(1)   NOT NULL DEFAULT '1',
    `show_records_per_page`               tinyint(1)   NOT NULL DEFAULT '1',
    `button_bar_sticky`                   tinyint(1)   NOT NULL DEFAULT '0',
    `show_preview_link`                   tinyint(1)   NOT NULL DEFAULT '0',
    `initial_list_limit`                  tinyint      NOT NULL DEFAULT '25',
    `save_button_title`                   varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `filter_exact_match`                  tinyint(1)   NOT NULL DEFAULT '1',
    `apply_button_title`                  varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `reference_id`    (`reference_id`),
    KEY `rand_date_update` (`rand_date_update`),
    KEY `tag`             (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_list_records
-- Tracks the state assignment of each data record within a CB view.
-- One row per (form, record, state) combination.
--
--   id            Auto-increment primary key.
--   form_id       CB view (#__contentbuilderng_forms.id).
--   record_id     Data record id (row in the BF storage table).
--   state_id      Assigned state (#__contentbuilderng_list_states.id).
--   reference_id  Source form id (denormalised for fast joins).
--   published     1 = record is published in this view; 0 = unpublished.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_list_records`
(
    `id`           bigint   NOT NULL AUTO_INCREMENT,
    `form_id`      int      NOT NULL DEFAULT '0',
    `record_id`    bigint   NOT NULL DEFAULT '0',
    `state_id`     int      NOT NULL DEFAULT '0',
    `reference_id` int      NOT NULL DEFAULT '0',
    `published`    tinyint(1) NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `form_id` (`form_id`, `record_id`, `state_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_list_states
-- Defines the available workflow states for a CB view.
-- Each view can have multiple named states displayed as colored badges.
--
--   id        Auto-increment primary key.
--   form_id   Parent CB view (#__contentbuilderng_forms.id).
--   title     Display label for the state (e.g. "Validated", "Pending").
--   color     Hex color code (without #) used for the badge background.
--   action    Optional action identifier triggered when entering this state.
--   published 1 = state is selectable; 0 = hidden.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_list_states`
(
    `id`        int          NOT NULL AUTO_INCREMENT,
    `form_id`   int          NOT NULL DEFAULT '0',
    `title`     varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `color`     varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `action`    varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published` tinyint      NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_rating_cache
-- Prevents duplicate ratings by tracking which IP already rated a record.
-- No primary key — the unique combination of (record_id, form_id, ip) is used.
--
--   record_id  Data record that was rated.
--   form_id    CB view the rating belongs to.
--   ip         Voter's IP address (IPv4 or IPv6, up to 50 chars).
--   date       Timestamp of the vote (used for cache expiry).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_rating_cache`
(
    `record_id` bigint     NOT NULL DEFAULT '0',
    `form_id`   int        NOT NULL DEFAULT '0',
    `ip`        varchar(50) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `date`      datetime   NULL DEFAULT NULL,
    KEY `record_id` (`record_id`, `form_id`, `ip`),
    KEY `date`      (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_records
-- Metadata table for each data record submitted through a CB view.
-- The actual field values live in the BF storage table; this table holds
-- CB-specific metadata (publish state, SEF, ratings, language, …).
--
--   id            Auto-increment primary key.
--   type          Source component type (mirrors forms.type).
--   record_id     Row id in the BF storage table (may differ from id).
--   reference_id  Source form id (mirrors forms.reference_id).
--   edited        Number of times the record has been edited.
--   sef           SEF URL segment for this record.
--   lang_code     Language tag ("*" = all, or specific BCP 47 code).
--   publish_up    Date/time from which the record is visible (NULL = now).
--   publish_down  Date/time after which the record is hidden (NULL = never).
--   last_update   Timestamp of last metadata update.
--   is_future     1 = record is scheduled (publish_up is in the future).
--   rating_sum    Sum of all star ratings received.
--   rating_count  Number of individual ratings received.
--   lastip        IP address of the last submitter/editor.
--   session_id    Joomla session id of the last submitter/editor.
--   published     1 = record is publicly visible; 0 = unpublished.
--   rand_date     Synthetic date used for random-sort ordering.
--   metakey       HTML meta keywords for the details page.
--   metadesc      HTML meta description for the details page.
--   robots        Robots directive for the details page (e.g. "noindex").
--   author        Meta author value for the details page.
--   rights        Meta rights/copyright value.
--   xreference    External cross-reference identifier.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_records`
(
    `id`           bigint       NOT NULL AUTO_INCREMENT,
    `type`         varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `record_id`    bigint       NOT NULL DEFAULT '0',
    `reference_id` int          NOT NULL DEFAULT '0',
    `edited`       int          NOT NULL DEFAULT '0',
    `sef`          varchar(50)  COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `lang_code`    varchar(7)   COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '*',
    `publish_up`   datetime     NULL DEFAULT NULL,
    `publish_down` datetime     NULL DEFAULT NULL,
    `last_update`  datetime     NULL DEFAULT NULL,
    `is_future`    tinyint(1)   NOT NULL DEFAULT '0',
    `rating_sum`   int          NOT NULL DEFAULT '0',
    `rating_count` int          NOT NULL DEFAULT '0',
    `lastip`       varchar(50)  COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `session_id`   varchar(32)  COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published`    tinyint(1)   NOT NULL DEFAULT '0',
    `rand_date`    datetime     NULL DEFAULT NULL,
    `metakey`      text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `metadesc`     text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `robots`       varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `author`       varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `rights`       varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `xreference`   varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `record_id`    (`record_id`),
    KEY `reference_id` (`reference_id`),
    KEY `type`         (`type`),
    KEY `rand_date`    (`rand_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_registered_users
-- Maps Joomla users to records they created via a CB registration form.
-- Used to enforce own_only access and to count user submissions.
--
--   id         Auto-increment primary key.
--   user_id    Joomla user id (#__users.id).
--   record_id  CB record id (#__contentbuilderng_records.id).
--   form_id    CB view (#__contentbuilderng_forms.id).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_registered_users`
(
    `id`        bigint NOT NULL AUTO_INCREMENT,
    `user_id`   int    NOT NULL DEFAULT '0',
    `record_id` bigint NOT NULL DEFAULT '0',
    `form_id`   int    NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`, `record_id`, `form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_resource_access
-- Access-control whitelist for protected resources (e.g. uploaded files).
-- A row grants a specific resource to a specific element in a form.
--
--   type         Resource type identifier (e.g. "file", "image").
--   form_id      CB view the resource belongs to.
--   element_id   CB element that owns the resource.
--   resource_id  Opaque resource identifier (filename, UUID, …).
--   hits         Number of times the resource has been accessed.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_resource_access`
(
    `type`        varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `form_id`     int          NOT NULL DEFAULT '0',
    `element_id`  int          NOT NULL DEFAULT '0',
    `resource_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `hits`        int          NOT NULL DEFAULT '0',
    UNIQUE KEY `type` (`type`, `element_id`, `resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_storages
-- External storage definitions: each storage points to a BF form whose
-- data table CB reads and writes directly (without going through BF views).
--
--   id           Auto-increment primary key.
--   name         Machine name / slug (unique).
--   title        Human-readable storage label.
--   bytable      1 = storage is addressed directly by table name.
--   ordering     Display order in the admin storage list.
--   created      Creation timestamp.
--   modified     Last-modification timestamp.
--   created_by   Username of creator.
--   modified_by  Username of last editor.
--   published    1 = storage is active; 0 = disabled.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_storages`
(
    `id`          int          NOT NULL AUTO_INCREMENT,
    `name`        varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title`       varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `bytable`     tinyint(1)   NOT NULL DEFAULT '0',
    `ordering`    int          NOT NULL DEFAULT '0',
    `created`     datetime     NULL DEFAULT CURRENT_TIMESTAMP,
    `modified`    datetime     NULL DEFAULT NULL,
    `created_by`  varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `modified_by` varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published`   tinyint(1)   NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_storage_fields
-- Column definitions for a direct-access CB storage.
-- Each row describes one column (or column group) available in the storage.
--
--   id               Auto-increment primary key.
--   storage_id       Parent storage (#__contentbuilderng_storages.id).
--   name             Column name in the underlying data table.
--   title            Human-readable column label.
--   is_group         1 = this row defines a repeating group of columns.
--   group_definition JSON schema describing the group's sub-columns.
--   ordering         Display order within the storage.
--   published        1 = column is active; 0 = hidden.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_storage_fields`
(
    `id`               int          NOT NULL AUTO_INCREMENT,
    `storage_id`       int          NOT NULL DEFAULT '0',
    `name`             varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title`            varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `is_group`         tinyint(1)   NOT NULL DEFAULT '0',
    `group_definition` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `ordering`         int          NOT NULL DEFAULT '0',
    `published`        tinyint(1)   NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `storage_id` (`storage_id`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_users
-- Per-user counters and verification state for each CB view.
-- Tracks how many records a user has submitted and whether they have
-- passed the optional verification gate for view / new / edit actions.
--
--   id                      Auto-increment primary key.
--   userid                  Joomla user id (#__users.id).
--   form_id                 CB view (#__contentbuilderng_forms.id).
--   records                 Number of records submitted by this user in this view.
--   verified_view           1 = user has passed the view verification gate.
--   verification_date_view  Timestamp when the view verification was granted.
--   verified_new            1 = user has passed the new-record verification gate.
--   verification_date_new   Timestamp when the new-record verification was granted.
--   verified_edit           1 = user has passed the edit verification gate.
--   verification_date_edit  Timestamp when the edit verification was granted.
--   limit_add               Override max-add limit for this user (0 = use view default).
--   limit_edit              Override max-edit limit for this user (0 = use view default).
--   published               1 = user entry is active; 0 = blocked.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_users`
(
    `id`                     int        NOT NULL AUTO_INCREMENT,
    `userid`                 int        NOT NULL DEFAULT '0',
    `form_id`                int        NOT NULL DEFAULT '0',
    `records`                int        NOT NULL DEFAULT '0',
    `verified_view`          tinyint(1) NOT NULL DEFAULT '0',
    `verification_date_view` datetime   NULL DEFAULT NULL,
    `verified_new`           tinyint(1) NOT NULL DEFAULT '0',
    `verification_date_new`  datetime   NULL DEFAULT NULL,
    `verified_edit`          tinyint(1) NOT NULL DEFAULT '0',
    `verification_date_edit` datetime   NULL DEFAULT NULL,
    `limit_add`              int        NOT NULL DEFAULT '0',
    `limit_edit`             int        NOT NULL DEFAULT '0',
    `published`              tinyint(1) NOT NULL DEFAULT '1',
    PRIMARY KEY (`id`),
    UNIQUE KEY `userid` (`userid`, `form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- -----------------------------------------------------------------------------
-- #__contentbuilderng_verifications
-- Pending and completed verification / payment transactions.
-- Each row represents one verification attempt initiated by a user.
--
--   id                  Auto-increment primary key.
--   verification_hash   Unique token sent to the user (e.g. by email or payment gateway).
--   start_date          Timestamp when the verification was initiated.
--   verification_date   Timestamp when the verification was completed (NULL = pending).
--   verification_data   JSON payload returned by the verification plugin.
--   create_invoice      1 = an invoice should be generated upon completion.
--   user_id             Joomla user id who initiated the verification.
--   plugin              Name of the verification plugin that handles this transaction.
--   ip                  IP address of the user at initiation time.
--   is_test             1 = test/sandbox transaction (not a real payment).
--   setup               JSON configuration snapshot used during this verification.
--   client              0 = frontend; 1 = administrator.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__contentbuilderng_verifications`
(
    `id`                  int          NOT NULL AUTO_INCREMENT,
    `verification_hash`   varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `start_date`          datetime     NULL DEFAULT NULL,
    `verification_date`   datetime     NULL DEFAULT NULL,
    `verification_data`   text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `create_invoice`      tinyint(1)   NOT NULL DEFAULT '0',
    `user_id`             int          NOT NULL DEFAULT '0',
    `plugin`              varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `ip`                  varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `is_test`             tinyint(1)   NOT NULL DEFAULT '0',
    `setup`               text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `client`              tinyint(1)   NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    KEY `verification_hash` (`verification_hash`),
    KEY `user_id`           (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
