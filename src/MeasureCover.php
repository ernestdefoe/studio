<?php

/*
 * This file is part of ernestdefoe/studio.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ErnestDefoe\Studio;

use Flarum\Queue\AbstractJob;
use GuzzleHttp\Client;
use Illuminate\Database\ConnectionInterface;

/**
 * Measures a cover hosted on another site, on the queue.
 *
 * Reads the first 128 KB at most (a picture's size is in its header), with a
 * short timeout, no redirects, and only from a public address: the address
 * came from a member's post, so it must not be able to reach this server's
 * own network. The host is resolved once and the request pinned to the
 * address that passed the check, so it cannot be re-pointed in between.
 *
 * A failure leaves the ratio at 0 (the gallery's 4:3 box) and is not
 * retried: the job is queued only when a cover is first worked out.
 */
final class MeasureCover extends AbstractJob
{
    private const BYTES = 131072;

    public function __construct(public int $discussionId, public string $url)
    {
    }

    public function handle(ConnectionInterface $db): void
    {
        $size = $this->fetch();

        if (! $size) {
            return;
        }

        $ratio = Covers::ratio($size[0], $size[1]);

        // Only if the cover is still this picture.
        $db->table('discussions')
            ->where('id', $this->discussionId)
            ->where('studio_cover', $this->url)
            ->update(['studio_ratio' => $ratio]);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function fetch(): ?array
    {
        $parts = parse_url($this->url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $ip = self::publicAddress($host);

        if (! $ip || ! in_array($port, [80, 443, 8080, 8443], true)) {
            return null;
        }

        try {
            $response = (new Client())->get($this->url, [
                'allow_redirects' => false,
                'connect_timeout' => 3,
                'timeout' => 5,
                'stream' => true,
                'http_errors' => false,
                'headers' => ['Range' => 'bytes=0-'.(self::BYTES - 1), 'Accept' => 'image/*'],
                'curl' => [CURLOPT_RESOLVE => [$host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip)]],
            ]);

            if (! in_array($response->getStatusCode(), [200, 206], true)) {
                return null;
            }

            $body = $response->getBody();
            $data = '';

            while (! $body->eof() && strlen($data) < self::BYTES) {
                $data .= $body->read(self::BYTES - strlen($data));
            }

            $body->close();
        } catch (\Throwable) {
            return null;
        }

        $size = @getimagesizefromstring($data);

        return $size && $size[0] > 0 && $size[1] > 0 ? [(int) $size[0], (int) $size[1]] : null;
    }

    /**
     * The host's address, if every address it has is a public one.
     *
     * 🚨 Global addresses only (PHP's FILTER_FLAG_GLOBAL_RANGE also refuses
     * CGNAT 100.64/10 and the documentation and benchmark ranges, which the
     * private/reserved flags let through), never NAT64 (64:ff9b::/96 and
     * 64:ff9b:1::/48 reach IPv4 addresses, 127.0.0.1 included), and never a
     * numeric host in a non-canonical form (0177.0.0.1, 0x7f.1, 2130706433).
     */
    public static function publicAddress(string $host): ?string
    {
        $host = trim($host, '[]');

        if (! filter_var($host, FILTER_VALIDATE_IP) && preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/i', $host)) {
            return null;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $v4 = @gethostbynamel($host) ?: [];
            $v6 = array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6');
            $addresses = array_merge($v4, $v6);
        }

        if (! $addresses) {
            return null;
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) || preg_match('/^64:ff9b:/i', $address)) {
                return null;
            }
        }

        return $addresses[0];
    }
}
