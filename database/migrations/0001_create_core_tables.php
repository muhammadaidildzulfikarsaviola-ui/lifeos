<?php
declare(strict_types=1);
return [
    'description'=>'Create local profile and application settings.',
    'up'=>static function(\PDO $pdo): void {
        $pdo->exec("CREATE TABLE IF NOT EXISTS profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid CHAR(36) NOT NULL,
            name VARCHAR(100) NOT NULL,
            timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Jakarta',
            currency CHAR(3) NOT NULL DEFAULT 'IDR',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY uq_profiles_uuid(uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            profile_id BIGINT UNSIGNED NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            UNIQUE KEY uq_app_settings_profile_key(profile_id,setting_key),
            CONSTRAINT fk_app_settings_profile FOREIGN KEY(profile_id) REFERENCES profiles(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        if((int)$pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn()===0){
            $hex=bin2hex(random_bytes(16));$uuid=substr($hex,0,8).'-'.substr($hex,8,4).'-4'.substr($hex,13,3).'-a'.substr($hex,17,3).'-'.substr($hex,20,12);
            $q=$pdo->prepare('INSERT INTO profiles(uuid,name,timezone,currency,created_at,updated_at) VALUES(:uuid,:name,:timezone,:currency,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $q->execute(['uuid'=>$uuid,'name'=>'Personal','timezone'=>'Asia/Jakarta','currency'=>'IDR']);
        }
    }
];
