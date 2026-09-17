<?php
if (!defined('ABSPATH')) {
    exit;
}

class UsuariosSaludMailer
{
    const SMTP_HOST = 'smtp.gmail.com';
    const SMTP_PORT = 587;
    const SMTP_USER = 'formulariospwe@unicauca.edu.co';
    const SMTP_PASS = 'uqle imym ythw yzsu';
    const FROM_NAME = 'Sistema Unicauca';

    /**
     * Envía el correo con el OTP.
     * Carga PHPMailer desde modules/usuarios-salud/vendor/autoload.php
     */
    public static function enviarOTP($email, $nombre, $otp)
    {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log('UsuariosSaludMailer: No existe vendor/autoload.php en ' . $autoload);
            return false;
        }
        require_once $autoload;

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('UsuariosSaludMailer: PHPMailer no está disponible');
            return false;
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet  = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->isSMTP();
            $mail->Host       = self::SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = self::SMTP_USER;
            $mail->Password   = self::SMTP_PASS;
            $mail->SMTPSecure = 'tls';
            $mail->Port       = self::SMTP_PORT;

            $mail->setFrom(self::SMTP_USER, self::FROM_NAME);
            $mail->addAddress($email);
            $mail->addReplyTo(self::SMTP_USER, self::FROM_NAME);

            $mail->isHTML(true);
            $mail->Subject = '🔐 Código de Verificación - Unicauca';
            $mail->Body    = self::plantillaOTP($nombre, $otp);
            $mail->AltBody = "Código de Verificación: {$otp}\n\nEste código expira en "
                           . UsuariosSaludOTP::DURACION_MINUTOS . " minutos.";

            $mail->send();
            return true;
        } catch (\Exception $e) {
            error_log('UsuariosSaludMailer error: ' . $e->getMessage());
            return false;
        }
    }

    private static function plantillaOTP($nombre, $otp)
    {
        $anio      = date('Y');
        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $minutos   = defined('UsuariosSaludOTP::DURACION_MINUTOS')
            ? UsuariosSaludOTP::DURACION_MINUTOS
            : 2;

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;padding:30px;border-radius:10px;">
    <h1 style="text-align:center;color:#2c3e50;">🔐 Código de Verificación</h1>
    <p>Hola <strong>{$nombreEsc}</strong>,</p>
    <p>Para completar tu verificación, ingresa el siguiente código:</p>
    <div style="background:#f8f9fa;border:2px dashed #3498db;padding:20px;text-align:center;font-size:32px;font-weight:bold;color:#2c3e50;letter-spacing:5px;margin:20px 0;border-radius:5px;">{$otp}</div>
    <p>Este código expirará en <strong>{$minutos} minutos</strong>.</p>
    <p style="color:#e74c3c;font-size:14px;">⚠️ Si no solicitaste este código, ignora este mensaje.</p>
    <div style="text-align:center;margin-top:30px;padding-top:20px;border-top:1px solid #eee;color:#888;font-size:12px;">
        <p>Este es un mensaje automático, por favor no responder.</p>
        <p>&copy; {$anio} Sistema de Validación - Unicauca</p>
    </div>
</div>
</body>
</html>
HTML;
    }
}