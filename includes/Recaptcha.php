<?php
if (!defined('ABSPATH')) {
    exit;
}

class UsuariosSaludRecaptcha
{
    // 🔑 Claves de tu reCAPTCHA v3
    const SITE_KEY   = '6LdgVMktAAAAADsdAZ0mz6DgYHj-hVaDq5lMzY5v';
    const SECRET_KEY = '6LdgVMktAAAAAOqP9bWQRy0Zh6ZtqJ9Be5rGpuny';

    // 🎯 Umbral mínimo de score (0.0 = bot, 1.0 = humano)
    // 0.5 es el estándar. Bájalo a 0.3 si rechaza usuarios legítimos.
    // Súbelo a 0.7 si quieres ser más estricto.
    const THRESHOLD = 0.5;

    // 🏷️ Nombre de la acción (debe coincidir entre frontend y backend)
    const ACTION = 'salud_login';

    /**
     * Valida un token de reCAPTCHA contra Google.
     * Devuelve true si el score >= THRESHOLD.
     */
    public static function validar($token)
    {
        if (empty($token)) {
            error_log('[RECAPTCHA] token vacío');
            return false;
        }

        $url  = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret'   => self::SECRET_KEY,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        // Preferir cURL (más confiable que file_get_contents)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $respuesta = curl_exec($ch);
            $codigo    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCurl = curl_error($ch);
            curl_close($ch);

            if ($codigo !== 200 || !$respuesta) {
                error_log('[RECAPTCHA] error cURL: ' . $errorCurl . ' (HTTP ' . $codigo . ')');
                return false;
            }
        } else {
            // Fallback sin cURL
            $opciones = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'content' => http_build_query($data),
                    'timeout' => 10,
                ],
            ];
            $contexto  = stream_context_create($opciones);
            $respuesta = @file_get_contents($url, false, $contexto);

            if ($respuesta === false) {
                error_log('[RECAPTCHA] error file_get_contents');
                return false;
            }
        }

        $json = json_decode($respuesta, true);
        if (!is_array($json)) {
            error_log('[RECAPTCHA] respuesta no es JSON válido: ' . $respuesta);
            return false;
        }

        $success = !empty($json['success']);
        $score   = isset($json['score'])  ? (float)$json['score'] : 0.0;
        $action  = $json['action']        ?? '';
        $errores = $json['error-codes']   ?? [];

        error_log('[RECAPTCHA] success=' . var_export($success, true)
            . ' score=' . $score
            . ' action=' . $action
            . ' errores=' . print_r($errores, true));

        if (!$success) {
            return false;
        }

        // Verificar que la acción coincida (evita reusar tokens de otra parte del sitio)
        if ($action !== self::ACTION) {
            error_log('[RECAPTCHA] action no coincide: esperado=' . self::ACTION . ' recibido=' . $action);
            return false;
        }

        if ($score < self::THRESHOLD) {
            error_log('[RECAPTCHA] score bajo: ' . $score . ' < ' . self::THRESHOLD);
            return false;
        }

        return true;
    }
}
