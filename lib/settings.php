<?php
require_once __DIR__ . '/db.php';

/**
 * Get a setting value from the database
 * @param string $key The setting key
 * @param mixed $default Default value if setting doesn't exist
 * @return mixed The setting value
 */
function get_setting(string $key, $default = null) {
    try {
        $result = db_query('SELECT setting_value, setting_type FROM site_settings WHERE setting_key = ?', [$key])->fetch();
        
        if (!$result) {
            return $default;
        }
        
        $value = $result['setting_value'];
        $type = $result['setting_type'];
        
        // Convert value based on type
        switch ($type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int)$value;
            case 'string':
            case 'text':
            default:
                return $value;
        }
    } catch (Exception $e) {
        error_log("Error getting setting '$key': " . $e->getMessage());
        return $default;
    }
}

/**
 * Set a setting value in the database
 * @param string $key The setting key
 * @param mixed $value The setting value
 * @param string $type The setting type (string, text, boolean, integer)
 * @param string $description Optional description
 * @return bool Success status
 */
function set_setting(string $key, $value, string $type = 'string', string $description = null): bool {
    try {
        // Convert value to string for storage
        if ($type === 'boolean') {
            $value = $value ? '1' : '0';
        } else {
            $value = (string)$value;
        }
        
        $sql = 'INSERT INTO site_settings (setting_key, setting_value, setting_type, description) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                setting_type = VALUES(setting_type),
                description = COALESCE(VALUES(description), description)';
        
        db_query($sql, [$key, $value, $type, $description]);
        return true;
    } catch (Exception $e) {
        error_log("Error setting '$key': " . $e->getMessage());
        return false;
    }
}

/**
 * Get all settings as an associative array
 * @return array Settings array
 */
function get_all_settings(): array {
    try {
        $results = db_query('SELECT setting_key, setting_value, setting_type FROM site_settings')->fetchAll();
        $settings = [];
        
        foreach ($results as $row) {
            $value = $row['setting_value'];
            $type = $row['setting_type'];
            
            // Convert value based on type
            switch ($type) {
                case 'boolean':
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'integer':
                    $value = (int)$value;
                    break;
                case 'string':
                case 'text':
                default:
                    // Keep as string
                    break;
            }
            
            $settings[$row['setting_key']] = $value;
        }
        
        return $settings;
    } catch (Exception $e) {
        error_log("Error getting all settings: " . $e->getMessage());
        return [];
    }
}

/**
 * Get SEO settings with fallbacks
 * @return array SEO settings
 */
function get_seo_settings(): array {
    return [
        'description' => get_setting('seo_default_description', 'Entdecke leckere Rezepte, Inspiration und Food-Tipps auf ' . APP_NAME . '.'),
        'keywords' => get_setting('seo_default_keywords', 'Rezepte, Kochen, Backen, Essen, Foodblog'),
        'author' => get_setting('seo_default_author', APP_NAME)
    ];
}

/**
 * Update SEO settings
 * @param array $seoData Array with description, keywords, author
 * @return bool Success status
 */
function update_seo_settings(array $seoData): bool {
    try {
        $success = true;
        
        if (isset($seoData['description'])) {
            $success &= set_setting('seo_default_description', $seoData['description'], 'text', 'Standard SEO-Beschreibung für die Website');
        }
        
        if (isset($seoData['keywords'])) {
            $success &= set_setting('seo_default_keywords', $seoData['keywords'], 'text', 'Standard SEO-Keywords für die Website');
        }
        
        if (isset($seoData['author'])) {
            $success &= set_setting('seo_default_author', $seoData['author'], 'string', 'Standard Autor für SEO-Meta-Tags');
        }
        
        return $success;
    } catch (Exception $e) {
        error_log("Error updating SEO settings: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a setting
 * @param string $key The setting key
 * @return bool Success status
 */
function delete_setting(string $key): bool {
    try {
        db_query('DELETE FROM site_settings WHERE setting_key = ?', [$key]);
        return true;
    } catch (Exception $e) {
        error_log("Error deleting setting '$key': " . $e->getMessage());
        return false;
    }
}
