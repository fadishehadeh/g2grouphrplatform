<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Field-level encryption using libsodium (XSalsa20-Poly1305).
 *
 * Each encrypted value is stored as: base64( nonce . ciphertext )
 * The nonce is random per encryption, so identical plaintexts produce
 * different ciphertexts — no information leaks through repeated values.
 *
 * Key is read from ENCRYPTION_KEY env var (64-char hex = 32 bytes).
 * If the key is missing the app throws immediately — no silent fallback.
 */
final class Encryption
{
    private string $key;

    public function __construct()
    {
        $hex = (string) ($_ENV['ENCRYPTION_KEY'] ?? getenv('ENCRYPTION_KEY') ?? '');

        if (strlen($hex) !== 64) {
            throw new RuntimeException(
                'ENCRYPTION_KEY must be a 64-character hex string (32 bytes). ' .
                'Generate one with: sodium_bin2hex(sodium_crypto_secretbox_keygen())'
            );
        }

        $bin = \sodium_hex2bin($hex);

        if ($bin === false || strlen($bin) !== \SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('ENCRYPTION_KEY is not a valid sodium secretbox key.');
        }

        $this->key = $bin;
    }

    /**
     * Encrypt a string field value.
     * Returns a base64-encoded string safe for VARCHAR/TEXT storage.
     * Returns null if the input is null or empty string.
     */
    public function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = \sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        // wipe plaintext from memory
        \sodium_memzero($plaintext);

        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt a previously encrypted field value.
     * Returns null if the stored value is null or empty.
     * Throws RuntimeException if the value is tampered or the key is wrong.
     */
    public function decrypt(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $decoded = base64_decode($stored, true);

        if ($decoded === false || strlen($decoded) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 1) {
            throw new RuntimeException('Encrypted field value is corrupt or truncated.');
        }

        $nonce      = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = \sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);

        if ($plaintext === false) {
            throw new RuntimeException(
                'Decryption failed — value may be tampered with or the key may have changed.'
            );
        }

        return $plaintext;
    }

    /**
     * Re-encrypt: decrypt then encrypt with the current key.
     * Useful after a key rotation migration.
     */
    public function reEncrypt(?string $stored): ?string
    {
        return $this->encrypt($this->decrypt($stored));
    }
}
