<?php
/* A small SMTP client: TLS (465) or STARTTLS (587), AUTH LOGIN, one message per connection.
   The hosting's mail server (smtp.stackmail.com, in the domain's SPF record) with the send-only
   address rezervace@khgporadenstvi.cz. No library: openssl and streams only. */
declare(strict_types=1);

function smtp_send(array $smtp, string $from, string $to, string $message): void
{
    $host = $smtp['host'];
    $port = (int)$smtp['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $s = @stream_socket_client(($port === 465 ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) throw new RuntimeException("SMTP connect $host:$port failed: $errstr");
    stream_set_timeout($s, 20);
    $read = function (int $want) use ($s): string {
        $all = '';
        while (($line = fgets($s, 1024)) !== false) {
            $all .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;   // the last line of a reply has a space after the code
        }
        if ((int)substr($all, 0, 3) !== $want) throw new RuntimeException('SMTP: expected ' . $want . ', got ' . trim(substr($all, 0, 200)));
        return $all;
    };
    $cmd = function (string $line, int $want) use ($s, $read): string {
        fwrite($s, $line . "\r\n");
        return $read($want);
    };
    try {
        $read(220);
        $me = 'khgporadenstvi.cz';
        $ehlo = $cmd("EHLO $me", 250);
        if ($port !== 465) {
            if (!str_contains($ehlo, 'STARTTLS')) throw new RuntimeException('SMTP: server offers no STARTTLS');
            $cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('SMTP: TLS failed');
            }
            $cmd("EHLO $me", 250);
        }
        $cmd('AUTH LOGIN', 334);
        $cmd(base64_encode($smtp['user']), 334);
        $cmd(base64_encode($smtp['pass']), 235);
        $cmd("MAIL FROM:<$from>", 250);
        $cmd("RCPT TO:<$to>", 250);
        $cmd('DATA', 354);
        // CRLF line ends, a leading dot doubled (RFC 5321 4.5.2)
        $body = preg_replace('/(?<!\r)\n/', "\r\n", $message);
        $body = preg_replace('/^\./m', '..', $body);
        fwrite($s, rtrim($body, "\r\n") . "\r\n.\r\n");
        $read(250);
        @fwrite($s, "QUIT\r\n");
    } finally {
        fclose($s);
    }
}
