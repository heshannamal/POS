<?php

namespace App\Services;

use InvalidArgumentException;

class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(): string
    {
        $bytes = random_bytes(20);
        $bits = '';
        for ($i = 0; $i < strlen($bytes); $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $group) {
            $result .= self::ALPHABET[bindec(str_pad($group, 5, '0'))];
        }
        return $result;
    }

    private function decode(string $secret): string
    {
        $secret = strtoupper(str_replace([' ', '-'], '', $secret));
        if ($secret === '' || preg_match('/[^A-Z2-7]/', $secret)) {
            throw new InvalidArgumentException('Invalid base32 secret');
        }
        $bits = '';
        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split(substr($bits, 0, strlen($bits) - strlen($bits) % 8), 8) as $octet) {
            $binary .= chr(bindec($octet));
        }
        return $binary;
    }

    public function code(string $secret, int $timestamp): string
    {
        $step = intdiv($timestamp, 30);
        $counter = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $mac = hash_hmac('sha1', $counter, $this->decode($secret), true);
        $offset = ord(substr($mac, -1)) & 0x0f;
        $number = unpack('N', substr($mac, $offset, 4))[1] & 0x7fffffff;
        return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public function verify(string $secret, string $input, ?int $time = null): bool
    {
        if (! preg_match('/^\d{6}$/D', $input)) {
            return false;
        }
        $time ??= time();
        for ($drift = -1; $drift <= 1; $drift++) {
            if (hash_equals($this->code($secret, $time + 30 * $drift), $input)) {
                return true;
            }
        }
        return false;
    }

    public function uri(string $secret, string $email): string
    {
        $issuer = (string) config('app.name', 'POS Cloud');
        $label = rawurlencode($issuer.':'.$email);
        return 'otpauth://totp/'.$label.'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    public function recoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(5)));
        }
        return $codes;
    }
}
