<?php

declare(strict_types=1);

namespace App\AI\Credentials;

use App\Secrets\SecretStoreInterface;

final readonly class SecretCipher implements SecretStoreInterface
{
    public function __construct(private string $appSecret)
    {
    }

    public function encrypt(string $plainText): string
    {
        if (!\function_exists('sodium_crypto_secretbox')) {
            throw new \RuntimeException('The sodium extension is required to encrypt LLM provider credentials.');
        }

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key());

        return base64_encode($nonce . $cipherText);
    }

    public function decrypt(string $payload): string
    {
        if (!\function_exists('sodium_crypto_secretbox_open')) {
            throw new \RuntimeException('The sodium extension is required to decrypt LLM provider credentials.');
        }

        $decoded = base64_decode($payload, true);
        if (false === $decoded || \strlen($decoded) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Stored LLM provider credential is not a valid encrypted payload.');
        }

        $nonce = substr($decoded, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = substr($decoded, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($cipherText, $nonce, $this->key());
        if (false === $plainText) {
            throw new \RuntimeException('Stored LLM provider credential could not be decrypted.');
        }

        return $plainText;
    }

    private function key(): string
    {
        return hash('sha256', $this->appSecret, true);
    }
}
