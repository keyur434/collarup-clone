<?php

class MailService
{
    private $config;

    public function __construct()
    {
        $this->config = config('services')['smtp'];
    }

    public function isConfigured()
    {
        return !empty($this->config['host']) && !empty($this->config['from_email']);
    }

    public function send($to, $subject, $htmlBody, $textBody = '')
    {
        if (!$this->isConfigured()) {
            return false;
        }
        if ($textBody === '') {
            $textBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));
        }

        if (!empty($this->config['host']) && $this->config['host'] !== 'mail') {
            return $this->sendSmtp($to, $subject, $htmlBody, $textBody);
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->formatAddress($this->config['from_email'], $this->config['from_name']),
            'Reply-To: ' . $this->config['from_email'],
        ];
        return @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
    }

    private function sendSmtp($to, $subject, $htmlBody, $textBody)
    {
        $host = $this->config['host'];
        $port = (int) $this->config['port'];
        $user = $this->config['username'];
        $pass = $this->config['password'];
        $from = $this->config['from_email'];
        $fromName = $this->config['from_name'];
        $secure = $port === 465 ? 'ssl' : 'tcp';

        $socket = @stream_socket_client(
            $secure . '://' . $host . ':' . $port,
            $errno,
            $errstr,
            30
        );
        if (!$socket) {
            throw new RuntimeException('SMTP connect failed: ' . $errstr);
        }

        $this->smtpExpect($socket, 220);
        $this->smtpCmd($socket, 'EHLO collarup.local', 250);

        if ($port === 587) {
            $this->smtpCmd($socket, 'STARTTLS', 220);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed');
            }
            $this->smtpCmd($socket, 'EHLO collarup.local', 250);
        }

        if ($user !== '') {
            $this->smtpCmd($socket, 'AUTH LOGIN', 334);
            $this->smtpCmd($socket, base64_encode($user), 334);
            $this->smtpCmd($socket, base64_encode($pass), 235);
        }

        $this->smtpCmd($socket, 'MAIL FROM:<' . $from . '>', 250);
        $this->smtpCmd($socket, 'RCPT TO:<' . $to . '>', 250);
        $this->smtpCmd($socket, 'DATA', 354);

        $boundary = 'collarup_' . md5(uniqid('', true));
        $message = "From: " . $this->formatAddress($from, $fromName) . "\r\n";
        $message .= "To: <{$to}>\r\n";
        $message .= "Subject: {$subject}\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n";
        $message .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n";
        $message .= "--{$boundary}--\r\n.";
        fwrite($socket, $message . "\r\n");
        $this->smtpExpect($socket, 250);
        $this->smtpCmd($socket, 'QUIT', 221);
        fclose($socket);
        return true;
    }

    private function smtpCmd($socket, $cmd, $expectCode)
    {
        fwrite($socket, $cmd . "\r\n");
        $this->smtpExpect($socket, $expectCode);
    }

    private function smtpExpect($socket, $code)
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        if ((int) substr($response, 0, 3) !== $code) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }

    private function formatAddress($email, $name)
    {
        $name = str_replace(['"', "\r", "\n"], '', $name);
        return '"' . $name . '" <' . $email . '>';
    }
}
