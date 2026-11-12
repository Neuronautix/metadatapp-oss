<?php

declare(strict_types=1);

namespace App\Secrets;

/**
 * Pluggable secret-storage layer for user-entered credentials (Connected Apps,
 * LLM provider keys, etc).
 *
 * Default implementation: sodium-based encryption to a database column. This is
 * sufficient for development, single-tenant, or self-hosted setups.
 *
 * For production deployments, swap the service alias for an adapter that
 * delegates to a managed secret store (HashiCorp Vault, AWS Secrets Manager,
 * GCP Secret Manager, Azure Key Vault, …). The contract intentionally stays
 * minimal so adapters can map `encrypt()` to a "store and return reference"
 * call, and `decrypt()` to a "resolve reference to plain text" call — no entity
 * needs to know whether the payload is ciphertext or an opaque reference.
 *
 * @see api/config/services.yaml — alias `App\Secrets\SecretStoreInterface`
 */
interface SecretStoreInterface
{
    /**
     * Persist `$plainText` and return an opaque payload that can later be
     * passed to {@see decrypt()} to recover the original value.
     *
     * Implementations may return ciphertext (sodium, libsodium, etc.) or an
     * opaque reference (vault path, AWS Secrets Manager ARN, …).
     */
    public function encrypt(string $plainText): string;

    /**
     * Resolve a payload previously returned by {@see encrypt()} back to plain
     * text. Throws if the payload is missing, tampered with, or unreadable.
     */
    public function decrypt(string $payload): string;
}
