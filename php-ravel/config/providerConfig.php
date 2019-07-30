<?php

/**
 * providerConfig Class comprises the authentication configuration for all ID Providers
 */
class providerConfig {

    /**
     * Function to prepare and return the configuration Array
     */
    public static function getConfig() {
        $configArray = array();
        $configArray = array(
            "base_url" => "https://service.ravelap.com/php-ravel-backend/php-ravel/hybrid.php", //Base URL should point to the hybrid.php
            "app_base_url" => "https://service.ravelap.com/php-ravel-backend/php-ravel", //Application base URL to point the nucleo app
            "image_upload_path" => "/var/www/html/php-ravel-backend/assets/",
            "video_upload_path" => "/var/www/html/php-ravel-backend/assets/",
            'image_mime' => array("image/jpeg", "image/png", "image/gif", "image/x-ms-bmp"),
            'video_mime' => array("video/mp4", "video/3gpp", "video/x-flv",
                "video/mov", "video/avi", "video/wmv", "video/mpg", "video/flv", "video/quicktime"),
            'max_size' => '500',
            "providers" => array(
                "Facebook" => array(
                    "enabled" => true,
                    "keys" => array("id" => "1659729300972042", "secret" => "9af326519ca7f8823f87664735972efe"),
                    "scope" => "email"
                ),
                "Twitter" => array(
                    "enabled" => true,
                    "keys" => array("key" => "0poVMNJRzUvH9ThFI58LieYZy", "secret" => "9iBkDUeHWXEIGNkSBejCRbNEX8a39H1GdgZSk9fNMH5oWeCK0N"),
                ),
                "Google" => array(
                    "enabled" => true,
                    "keys" => array("id" => "186832052872-4borbin8lms1i0vhtl5cbb806l6vaqto.apps.googleusercontent.com", "secret" => "lBgA8q0BdbqfiRzCaTU8hycB"),
                ),
                "Instagram" => array(
                    "enabled" => true,
                    "keys" => array("id" => "bc9aecc083224cafbb739b9ec8a8bc5d", "secret" => "3a0397fdb29d4415940b90f22564ef37"),
                )
            ),
            'GoogleUri' => 'https://www.googleapis.com/oauth2/v1/userinfo',
            'GoogleContact' => 'https://www.google.com/m8/feeds/contacts/default',
            'FacebookUri' => 'https://graph.facebook.com/v2.5/me',
            'InstagramUri' => 'https://api.instagram.com/v1/users',
            'TwitterUri' => ''
        );
        return $configArray;
    }

}
