<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\SiteVerificationToken;
use Psr\Http\Message\ResponseInterface;

class SiteVerificationController extends AbstractController
{
    public function verify(): ResponseInterface
    {
        $source = trim((string) $this->request->query('source', ''));
        $challenge = trim((string) $this->request->query('challenge', ''));

        if ($source === '' || $challenge === '') {
            return $this->response->json([
                'token' => null,
                'message' => 'source 和 challenge 参数不能为空',
            ])->withStatus(400);
        }

        $domain = $this->getDomainWithPort();
        $token = SiteVerificationToken::generate($domain, $source, $challenge);

        $normalizedDomain = strtolower($domain);
        $normalizedSource = strtolower($source);
        $payload = sprintf('%s|%s|%s', $normalizedDomain, $normalizedSource, $challenge);

        return $this->response->json([
            'token' => $token,
            'domain' => $normalizedDomain,
            'source' => $normalizedSource,
            'challenge' => $challenge,
            'payload' => $payload,
            'algorithm' => 'sha256',
        ]);
    }

    private function getDomainWithPort(): string
    {
        $uri = $this->request->getUri();
        $host = strtolower($uri->getHost());
        $port = $uri->getPort();

        if ($port && ! in_array($port, [80, 443], true)) {
            return $host . ':' . $port;
        }

        return $host;
    }
}


