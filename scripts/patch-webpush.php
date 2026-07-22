<?php
/**
 * Automatically patches vendor packages for OpenSSL 3 AL2023 / PHP 8.2+ serverless compatibility.
 * When openssl_pkey_new is called for EC curves (prime256v1 / P-256) on OpenSSL 3,
 * omitting default_bits / private_key_bits causes:
 * "openssl_pkey_new(): Private key length must be at least 384 bits, configured to 0"
 */

// 1. Patch minishlink/web-push Encryption.php
$webPushFile = __DIR__ . '/../vendor/minishlink/web-push/src/Encryption.php';
if (file_exists($webPushFile)) {
    $content = file_get_contents($webPushFile);
    if (strpos($content, "'default_bits'     => 2048") === false) {
        $target = "'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,";
        $replacement = "'curve_name'       => 'prime256v1',
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'default_bits'     => 2048,
            'private_key_bits' => 2048,";
        
        $newContent = str_replace($target, $replacement, $content);
        if ($newContent !== $content) {
            file_put_contents($webPushFile, $newContent);
            echo "[patch-webpush] Patched minishlink/web-push Encryption.php successfully.\n";
        } else {
            // Fallback regex replacement
            $pattern = "/openssl_pkey_new\(\s*\[\s*'curve_name'\s*=>\s*'prime256v1',\s*'private_key_type'\s*=>\s*OPENSSL_KEYTYPE_EC,?\s*\]\s*\)/";
            $replace = "openssl_pkey_new([\n            'curve_name'       => 'prime256v1',\n            'private_key_type' => OPENSSL_KEYTYPE_EC,\n            'default_bits'     => 2048,\n            'private_key_bits' => 2048,\n        ])";
            $newContent2 = preg_replace($pattern, $replace, $content);
            if ($newContent2 !== null && $newContent2 !== $content) {
                file_put_contents($webPushFile, $newContent2);
                echo "[patch-webpush] Patched minishlink/web-push Encryption.php via regex successfully.\n";
            }
        }
    } else {
        echo "[patch-webpush] minishlink/web-push Encryption.php is already patched.\n";
    }
}

// 2. Ensure web-token/jwt-library ECKey.php also has default_bits => 2048
$ecKeyFile = __DIR__ . '/../vendor/web-token/jwt-library/Core/Util/ECKey.php';
if (file_exists($ecKeyFile)) {
    $content = file_get_contents($ecKeyFile);
    if (strpos($content, "'default_bits' => 2048") === false && strpos($content, "'private_key_bits' => 2048") !== false) {
        $newContent = str_replace(
            "'private_key_bits' => 2048,",
            "'private_key_bits' => 2048,\n            'default_bits' => 2048,",
            $content
        );
        if ($newContent !== $content) {
            file_put_contents($ecKeyFile, $newContent);
            echo "[patch-webpush] Patched web-token/jwt-library ECKey.php with default_bits successfully.\n";
        }
    }
}
