CREATE TABLE IF NOT EXISTS `#__contentbuilderng_articles`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `article_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `type`
    varchar
(
    55
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `reference_id` int NOT NULL DEFAULT '0',
    `record_id` bigint NOT NULL DEFAULT '0',
    `form_id` int NOT NULL DEFAULT '0',
    `last_update` datetime NULL DEFAULT NULL,
    PRIMARY KEY
(
    `id`
),
    KEY `record_id`
(
    `record_id`,
    `form_id`
),
    KEY `article_id`
(
    `article_id`,
    `record_id`
),
    KEY `record_id_2`
(
    `record_id`
),
    KEY `type`
(
    `type`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_elements`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `reference_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `type`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `change_type` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `options` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_init_script` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_action_script` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `custom_validation_script` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `validation_message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `default_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `hint` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `label` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `list_include` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `search_include` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `item_wrapper` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `wordwrap` int NOT NULL DEFAULT '0',
    `linkable` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `editable` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `validations` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `published` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `order_type` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `ordering` int NOT NULL DEFAULT '0',
    PRIMARY KEY
(
    `id`
),
    KEY `reference_id`
(
    `reference_id`
),
    KEY `form_id`
(
    `form_id`,
    `reference_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS `#__contentbuilderng_forms`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `type`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `reference_id` int NOT NULL DEFAULT '0',
    `name` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `tag` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `details_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `details_prepare` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `editable_template` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `editable_prepare` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `created` datetime NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime NULL DEFAULT NULL,
    `created_by` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `modified_by` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `metadata` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `export_xls` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `print_button` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `show_id_column` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `use_view_name_as_title` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `display_in` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `edit_button` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `new_button` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_state` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_publish` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_language` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_article` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_author` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `list_last_modification` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `cb_show_author` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `cb_show_top_bar` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `cb_show_bottom_bar` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `cb_show_details_top_bar` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `cb_show_details_bottom_bar` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `show_back_button` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `cb_filter_in_title` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `cb_prefix_in_title` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `select_column` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `published_only` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `own_only` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `own_only_fe` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `ordering` int NOT NULL DEFAULT '0',
    `intro_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `default_section` int NOT NULL DEFAULT '0',
    `default_category` int NOT NULL DEFAULT '0',
    `default_lang_code` varchar
(
    7
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '*',
    `default_lang_code_ignore` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `create_articles` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `published` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `initial_sort_order` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_sort_order2` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_sort_order3` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '-1',
    `initial_order_dir` varchar
(
    4
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'desc',
    `title_field` int NOT NULL DEFAULT '0',
    `delete_articles` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `edit_by_type` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `email_notifications` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `email_update_notifications` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `limited_article_options` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `limited_article_options_fe` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `upload_directory` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `protect_upload_directory` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `last_update` datetime NULL DEFAULT NULL,
    `limit_add` int NOT NULL DEFAULT '0',
    `limit_edit` int NOT NULL DEFAULT '0',
    `verification_required_view` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_days_view` float NOT NULL DEFAULT '0',
    `verification_required_new` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_days_new` float NOT NULL DEFAULT '0',
    `verification_required_edit` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_days_edit` float NOT NULL DEFAULT '0',
    `verification_url_view` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `verification_url_new` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `verification_url_edit` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `show_all_languages_fe` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `default_publish_up_days` int NOT NULL DEFAULT '0',
    `default_publish_down_days` int NOT NULL DEFAULT '0',
    `default_access` int NOT NULL DEFAULT '0',
    `default_featured` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `email_admin_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_subject` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_alternative_from` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_alternative_fromname` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_admin_recipients` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_recipients_attach_uploads` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_admin_html` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `email_template` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_subject` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_alternative_from` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `email_alternative_fromname` varchar
(
    255
) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
    `email_recipients` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_recipients_attach_uploads` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `email_html` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `act_as_registration` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `registration_username_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_password_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_password_repeat_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_name_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_email_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_email_repeat_field` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `auto_publish` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `force_login` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `force_url` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `registration_bypass_plugin` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_bypass_plugin_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `registration_bypass_verification_name` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `registration_bypass_verify_view` varchar
(
    32
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `theme_plugin` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `list_rating` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `rating_slots` tinyint
(
    1
) NOT NULL DEFAULT '5',
    `rand_date_update` datetime NULL DEFAULT NULL,
    `rand_update` int NOT NULL DEFAULT '86400',
    `article_record_impact_publish` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `article_record_impact_language` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `allow_external_filter` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `show_filter` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `show_records_per_page` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `button_bar_sticky` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `show_preview_link` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `initial_list_limit` tinyint NOT NULL DEFAULT '25',
    `save_button_title` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `filter_exact_match` tinyint
(
    1
) NOT NULL DEFAULT '1',
    `apply_button_title` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    PRIMARY KEY
(
    `id`
),
    KEY `reference_id`
(
    `reference_id`
),
    KEY `rand_date_update`
(
    `rand_date_update`
),
    KEY `tag`
(
    `tag`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS `#__contentbuilderng_list_records`
(
    `id`
    bigint
    NOT
    NULL
    AUTO_INCREMENT,
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `record_id`
    bigint
    NOT
    NULL
    DEFAULT
    '0',
    `state_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `reference_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `published`
    tinyint
(
    1
) NOT NULL DEFAULT '0',
    PRIMARY KEY
(
    `id`
),
    KEY `form_id`
(
    `form_id`,
    `record_id`,
    `state_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_list_states`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `title`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `color` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `action` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published` tinyint NOT NULL DEFAULT '0',
    PRIMARY KEY
(
    `id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_rating_cache`
(
    `record_id`
    bigint
    NOT
    NULL
    DEFAULT
    '0',
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `ip`
    varchar
(
    50
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `date` datetime NULL DEFAULT NULL,
    KEY `record_id`
(
    `record_id`,
    `form_id`,
    `ip`
),
    KEY `date`
(
    `date`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_records`
(
    `id`
    bigint
    NOT
    NULL
    AUTO_INCREMENT,
    `type`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `record_id` bigint NOT NULL DEFAULT '0',
    `reference_id` int NOT NULL DEFAULT '0',
    `edited` int NOT NULL DEFAULT '0',
    `sef` varchar
(
    50
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `lang_code` varchar
(
    7
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '*',
    `publish_up` datetime NULL DEFAULT NULL,
    `publish_down` datetime NULL DEFAULT NULL,
    `last_update` datetime NULL DEFAULT NULL,
    `is_future` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `rating_sum` int NOT NULL DEFAULT '0',
    `rating_count` int NOT NULL DEFAULT '0',
    `lastip` varchar
(
    50
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `session_id` varchar
(
    32
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `rand_date` datetime NULL DEFAULT NULL,
    `metakey` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `metadesc` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `robots` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `author` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `rights` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `xreference` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    PRIMARY KEY
(
    `id`
),
    KEY `record_id`
(
    `record_id`
),
    KEY `reference_id`
(
    `reference_id`
),
    KEY `type`
(
    `type`
),
    KEY `rand_date`
(
    `rand_date`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;


CREATE TABLE IF NOT EXISTS `#__contentbuilderng_registered_users`
(
    `id`
    bigint
    NOT
    NULL
    AUTO_INCREMENT,
    `user_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `record_id`
    bigint
    NOT
    NULL
    DEFAULT
    '0',
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    PRIMARY
    KEY
(
    `id`
),
    KEY `user_id`
(
    `user_id`,
    `record_id`,
    `form_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_resource_access`
(
    `type` varchar
(
    100
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `form_id` int NOT NULL DEFAULT '0',
    `element_id` int NOT NULL DEFAULT '0',
    `resource_id` varchar
(
    100
) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `hits` int NOT NULL DEFAULT '0',
    UNIQUE KEY `type`
(
    `type`,
    `element_id`,
    `resource_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_storages`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `name`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `bytable` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `ordering` int NOT NULL DEFAULT '0',
    `created` datetime NULL DEFAULT CURRENT_TIMESTAMP,
    `modified` datetime NULL DEFAULT NULL,
    `created_by` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `modified_by` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `published` tinyint
(
    1
) NOT NULL DEFAULT '0',
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `name`
(
    `name`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_storage_fields`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `storage_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `name`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `title` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `is_group` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `group_definition` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `ordering` int NOT NULL DEFAULT '0',
    `published` tinyint
(
    1
) NOT NULL DEFAULT '1',
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `storage_id`
(
    `storage_id`,
    `name`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_users`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `userid`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `form_id`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `records`
    int
    NOT
    NULL
    DEFAULT
    '0',
    `verified_view`
    tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_date_view` datetime NULL DEFAULT NULL,
    `verified_new` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_date_new` datetime NULL DEFAULT NULL,
    `verified_edit` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `verification_date_edit` datetime NULL DEFAULT NULL,
    `limit_add` int NOT NULL DEFAULT '0',
    `limit_edit` int NOT NULL DEFAULT '0',
    `published` tinyint
(
    1
) NOT NULL DEFAULT '1',
    PRIMARY KEY
(
    `id`
),
    UNIQUE KEY `userid`
(
    `userid`,
    `form_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `#__contentbuilderng_verifications`
(
    `id`
    int
    NOT
    NULL
    AUTO_INCREMENT,
    `verification_hash`
    varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `start_date` datetime NULL DEFAULT NULL,
    `verification_date` datetime NULL DEFAULT NULL,
    `verification_data` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `create_invoice` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `user_id` int NOT NULL DEFAULT '0',
    `plugin` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `ip` varchar
(
    255
) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
    `is_test` tinyint
(
    1
) NOT NULL DEFAULT '0',
    `setup` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
    `client` tinyint
(
    1
) NOT NULL DEFAULT '0',
    PRIMARY KEY
(
    `id`
),
    KEY `verification_hash`
(
    `verification_hash`
),
    KEY `user_id`
(
    `user_id`
)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE =utf8mb4_0900_ai_ci;
