<?php
// For Composer  but due to dependency I just move forward to other solution
// class ZeeCreatives_MultiSites_JWT {
//     private static $secret_key;

//     public static function init() {
//         self::$secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : 'your-secret-key';
//     }

//     public static function generate_token($user_id) {
//         $issued_at = time();
//         $expiration = $issued_at + (DAY_IN_SECONDS * 7); // Token expires in 7 days

//         $payload = array(
//             'iss' => get_bloginfo('url'),
//             'iat' => $issued_at,
//             'exp' => $expiration,
//             'user_id' => $user_id,
//         );

//         return JWT::encode($payload, self::$secret_key, 'HS256');
//     }

//     public static function validate_token($token) {
//         try {
//             $decoded = JWT::decode($token, new Key(self::$secret_key, 'HS256'));
//             return $decoded->user_id;
//         } catch (Exception $e) {
//             return false;
//         }
//     }
// }

// ZeeCreatives_MultiSites_JWT::init();

// class ZeeCreatives_MultiSites_JWT {
//     private static $secret_key;

//     public static function init() {
//         self::$secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : wp_generate_password(64, true, true);
//     }

//     public static function generate_token($user_id) {
//         $issued_at = time();
//         $expiration = $issued_at + (DAY_IN_SECONDS * 7); // Token expires in 7 days

//         $payload = array(
//             'iss' => get_bloginfo('url'),
//             'iat' => $issued_at,
//             'exp' => $expiration,
//             'user_id' => $user_id,
//         );

//         $header = self::encode(wp_json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
//         $payload = self::encode(wp_json_encode($payload));
//         $signature = self::encode(hash_hmac('sha256', "$header.$payload", self::$secret_key, true));

//         return "$header.$payload.$signature";
//     }

//     public static function validate_token($token) {
//         $parts = explode('.', $token);
//         if (count($parts) != 3) {
//             return false;
//         }

//         list($header, $payload, $signature) = $parts;

//         $valid_signature = self::encode(hash_hmac('sha256', "$header.$payload", self::$secret_key, true));
//         if ($signature !== $valid_signature) {
//             return false;
//         }

//         $payload = json_decode(self::decode($payload), true);
//         if (!isset($payload['exp']) || $payload['exp'] < time()) {
//             return false;
//         }

//         return $payload['user_id'];
//     }

//     private static function encode($data) {
//         return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
//     }

//     private static function decode($data) {
//         return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
//     }
// }

// ZeeCreatives_MultiSites_JWT::init();


class ZeeCreatives_MultiSites_JWT {
    private static $secret_key;

    public static function init() {
        self::$secret_key = defined('JWT_AUTH_SECRET_KEY') ? JWT_AUTH_SECRET_KEY : wp_generate_password(64, true, true);
    }

    public static function generate_token($user_id) {
        $issued_at = time();
        $expiration = $issued_at + (DAY_IN_SECONDS * 7); // Token expires in 7 days

        $payload = array(
            'iss' => get_bloginfo('url'),
            'iat' => $issued_at,
            'exp' => $expiration,
            'user_id' => $user_id,
        );

        $header = self::encode(wp_json_encode(array('typ' => 'JWT', 'alg' => 'HS256')));
        $payload = self::encode(wp_json_encode($payload));
        $signature = self::encode(hash_hmac('sha256', "$header.$payload", self::$secret_key, true));

        return "$header.$payload.$signature";
    }

    public static function validate_token($token) {
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }

        list($header, $payload, $signature) = $parts;

        $valid_signature = self::encode(hash_hmac('sha256', "$header.$payload", self::$secret_key, true));
        if ($signature !== $valid_signature) {
            return false;
        }

        $payload = json_decode(self::decode($payload), true);
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            return false;
        }

        return $payload['user_id'];
    }

    private static function encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}

// Hook the initialization to a point where WordPress core functions are available
add_action('init', function() {
    ZeeCreatives_MultiSites_JWT::init();
});

