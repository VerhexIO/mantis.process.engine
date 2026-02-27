<?php
# MantisBT Configuration - Docker Development Environment

# Database
$g_hostname      = 'db';
$g_db_type       = 'mysqli';
$g_database_name = 'mantis';
$g_db_username   = 'mantis';
$g_db_password   = 'mantis123';

# Paths
$g_path          = 'http://localhost:8080/';
$g_short_path    = '/';

# Email - MailHog SMTP
$g_phpMailer_method    = PHPMAILER_METHOD_SMTP;
$g_smtp_host           = 'mailhog';
$g_smtp_port           = 1025;
$g_smtp_connection_mode = '';
$g_smtp_username       = '';
$g_smtp_password       = '';
$g_webmaster_email     = 'admin@mantisbt.local';
$g_from_email          = 'noreply@mantisbt.local';
$g_return_path_email   = 'admin@mantisbt.local';
$g_from_name           = 'MantisBT';
$g_email_receive_own   = ON;
$g_email_send_using_cronjob = OFF;

# Anonymous access
$g_allow_anonymous_login = OFF;

# Misc
$g_crypto_master_salt = 'mantis-process-engine-dev-salt-2024';
$g_default_timezone   = 'Europe/Istanbul';

# Logging (development)
$g_log_level = LOG_EMAIL | LOG_PLUGIN;
$g_log_destination = 'file:/tmp/mantisbt.log';
