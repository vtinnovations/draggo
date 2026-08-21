<?php

declare(strict_types=1);

/*
 * Draggo
 *
 * Package: vtinnovations/draggo
 * Copyright: V&T Innovations Team
 * Licence: LGPL-3.0-or-later
 * Website: https://v-t.one
 */

namespace Vtinnovations\Draggo\Settings;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The trusted host inventory for this installation.
 *
 * Draggo is an instance-wide package, so the inventory is the union of every
 * hostname configured on a Contao website root (tl_page.dns). It comes from
 * configuration, never from a request header a visitor controls.
 *
 * Normalisation changes REPRESENTATION ONLY — lowercase, one trailing dot, an
 * explicit port, IDN to Punycode. It never changes which host is meant:
 * "www." is not stripped, nothing is reduced to a registrable domain, no alias
 * or CNAME is followed. example.com, www.example.com, shop.example.com and
 * admin.shop.example.com are four distinct identities, and only an exact member
 * of the signed set authorises anything.
 *
 * When no root page declares a DNS name — the common single-site Contao setup
 * that answers on whatever host it is reached at — the inventory falls back to
 * the current trusted request host. That is Symfony's validated host (trusted
 * proxies and trusted_hosts applied), and it still has to appear in the signed
 * host set, so it grants nothing the registry has not already bound.
 */
final class HostInventory
{
    public function __construct(
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Configured hosts for this instance: unique, normalised, sorted.
     *
     * @return list<string>
     */
    public function configured(): array
    {
        $hosts = [];

        foreach ($this->rootDomains() as $dns) {
            $host = self::normalise($dns);

            if (null !== $host) {
                $hosts[$host] = true;
            }
        }

        if ([] === $hosts) {
            $current = $this->currentHost();

            if (null !== $current) {
                $hosts[$current] = true;
            }
        }

        $out = array_keys($hosts);
        sort($out, SORT_STRING);

        return $out;
    }

    /**
     * The host to send with an activation or refresh.
     *
     * Deterministic so a background job and the session signal agree with what
     * the administrator activated: the current trusted host when it is part of
     * the inventory, otherwise the first configured host. Null means there is
     * nothing to activate against.
     */
    public function verificationHost(): ?string
    {
        $configured = $this->configured();

        if ([] === $configured) {
            return null;
        }

        $current = $this->currentHost();

        if (null !== $current && \in_array($current, $configured, true)) {
            return $current;
        }

        return $configured[0];
    }

    /**
     * The current request's host, as validated by the framework. Null outside a
     * request (CLI, worker) or when the host is rejected as suspicious.
     */
    public function currentHost(): ?string
    {
        $request = $this->requestStack->getMainRequest();

        if (null === $request) {
            return null;
        }

        try {
            // getHost() applies trusted-proxy and trusted-host policy and
            // throws on a host that fails validation.
            return self::normalise($request->getHost());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Exact set intersection between the configured inventory and a signed host
     * set. One match is enough to authorise the instance; zero matches means
     * this installation is not covered.
     *
     * @param list<string> $signed already-normalised signed hosts
     */
    public function match(array $signed): ?string
    {
        foreach ($this->configured() as $host) {
            if (\in_array($host, $signed, true)) {
                return $host;
            }
        }

        return null;
    }

    /**
     * Normalise representation without widening scope.
     *
     * Rejects empty values, wildcards, userinfo, paths and anything that is not
     * a plausible hostname. A bare IP literal is accepted as-is: it identifies
     * exactly one host and cannot be confused with a domain.
     */
    public static function normalise(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $host = trim($value);

        if ('' === $host || \strlen($host) > 253) {
            return null;
        }

        // A full URL is tolerated in configuration; take only its host.
        if (str_contains($host, '://')) {
            $parsed = parse_url($host, PHP_URL_HOST);

            if (!\is_string($parsed) || '' === $parsed) {
                return null;
            }

            $host = $parsed;
        }

        // Strip an explicit port, but never anything that changes the labels.
        if (preg_match('/^(?<host>.+?):\d+$/', $host, $m) && !str_contains($m['host'], ':')) {
            $host = $m['host'];
        }

        $host = strtolower($host);

        // One trailing root dot only.
        if (str_ends_with($host, '.')) {
            $host = substr($host, 0, -1);
        }

        if ('' === $host) {
            return null;
        }

        // IDN to a consistent ASCII form. Without the intl extension a
        // non-ASCII name is refused rather than compared in mixed encodings.
        if (preg_match('/[^\x20-\x7E]/', $host)) {
            if (!\function_exists('idn_to_ascii')) {
                return null;
            }

            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

            if (!\is_string($ascii) || '' === $ascii) {
                return null;
            }

            $host = strtolower($ascii);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        // Labels only: letters, digits, hyphen, dot. No wildcard, no userinfo,
        // no path, no leading/trailing/doubled dots or hyphens at label edges.
        if (!preg_match('/^(?!-)[a-z0-9-]{1,63}(?<!-)(\.(?!-)[a-z0-9-]{1,63}(?<!-))*$/', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * @return list<string>
     */
    private function rootDomains(): array
    {
        try {
            $rows = $this->connection->fetchFirstColumn(
                "SELECT dns FROM tl_page WHERE type = 'root' AND dns != ''",
            );
        } catch (\Throwable) {
            // A missing or unavailable table must not crash a page render; it
            // simply means no configured inventory yet.
            return [];
        }

        return array_map(static fn (mixed $v): string => (string) $v, $rows);
    }
}
