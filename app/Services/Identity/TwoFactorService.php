<?php

namespace App\Services\Identity;

use App\Models\User;

class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Generates a cryptographically random RFC 6238 Base32 secret. */
    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    /** Verifies a six-digit TOTP with a small clock-skew window. */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Builds an otpauth URI accepted by common authenticator applications. */
    public function provisioningUri(User $user, string $secret, string $issuer = 'AutoCar'): string
    {
        $label = rawurlencode($issuer.':'.($user->email ?: $user->mobile ?: (string) $user->id));

        return 'otpauth://totp/'.$label.'?secret='.rawurlencode($secret).'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
    }

    /** Calculates one HOTP value for the supplied 30-second counter. */
    private function code(string $secret, int $counter): string
    {
        $key = $this->base32Decode($secret);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** Encodes raw bytes as unpadded Base32. */
    private function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        $result = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0');
            $result .= self::ALPHABET[bindec($chunk)];
        }

        return $result;
    }

    /** Decodes an unpadded Base32 secret into raw bytes. */
    private function base32Decode(string $value): string
    {
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
        $bits = '';
        foreach (str_split($value) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }
        $binary = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $binary .= chr(bindec($chunk));
            }
        }

        return $binary;
    }
}
