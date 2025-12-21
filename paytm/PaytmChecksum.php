<?php
/**
 * Minimal PaytmChecksum helper (compatible with Paytm PHP examples)
 * This implementation is a small adaptation of the checksum logic used by Paytm's SDK
 * and provides generateSignature() and verifySignature() methods.
 */
class PaytmChecksum {
    private static function encrypt($input, $key) {
        $iv = "@@@@&&&&####$$$$"; // Paytm IV
        $output = openssl_encrypt($input, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($output);
    }

    private static function decrypt($input, $key) {
        $iv = "@@@@&&&&####$$$$";
        $decoded = base64_decode($input);
        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted;
    }

    private static function getArray2Str($array) {
        ksort($array);
        $str = '';
        foreach ($array as $key => $val) {
            if ($key === 'CHECKSUMHASH') continue;
            $str .= ($val === null ? '' : $val) . '|';
        }
        return rtrim($str, '|');
    }

    public static function generateSignature($params, $key) {
        if (!is_array($params)) {
            // if string provided, use as-is
            $str = $params;
        } else {
            $str = self::getArray2Str($params);
        }
        $salt = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 4);
        $finalString = $str . '|' . $salt;
        $hash = hash('sha256', $finalString);
        $hashString = $hash . $salt;
        $checksum = self::encrypt($hashString, $key);
        return $checksum;
    }

    public static function verifySignature($params, $key, $checksum) {
        if (is_array($params)) {
            $str = self::getArray2Str($params);
        } else {
            $str = $params;
        }
        $decryptHash = self::decrypt($checksum, $key);
        if ($decryptHash === false) return false;

        $salt = substr($decryptHash, -4);
        $finalString = $str . '|' . $salt;
        $calculatedHash = hash('sha256', $finalString);
        $calculated = $calculatedHash . $salt;
        return ($calculated === $decryptHash);
    }
}

?>