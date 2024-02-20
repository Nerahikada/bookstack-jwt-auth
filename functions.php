<?php

declare(strict_types=1);

const JWT_HEADER_NAME = 'Cf-Access-Jwt-Assertion';
const JWT_KEYS_URI = 'https://YOUR_TEAM_NAME.cloudflareaccess.com/cdn-cgi/access/certs';
const APPLICATION_AUD = 'YOUR_APPLICATION_AUD';

use BookStack\Access\LoginService;
use BookStack\Access\RegistrationService;
use BookStack\Facades\Theme;
use BookStack\Theming\ThemeEvents;
use Firebase\JWT\CachedKeySet;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Cache\Adapter\Psr16Adapter;

require __DIR__ . '/vendor/autoload.php';

Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {
    if (auth()->check()) return;

    $jwt = $request->headers->get(JWT_HEADER_NAME);
    if (!is_string($jwt)) return;

    $keySet = new CachedKeySet(JWT_KEYS_URI, new Client(), new HttpFactory(), new Psr16Adapter(Cache::driver()), null, true);
    $payload = JWT::decode($jwt, $keySet);
    if (($payload->aud[0] ?? null) !== APPLICATION_AUD) return;

    if (property_exists($payload, 'email')) {
        /** @var RegistrationService $registrationService */
        $registrationService = app()->make(RegistrationService::class);
        $user = $registrationService->findOrRegister(explode('@', $payload->email)[0], $payload->email, $payload->email);
        /** @var LoginService $loginService */
        $loginService = app()->make(LoginService::class);
        $issuer = $payload->iss ?? 'Unknown issuer';
        $loginService->login($user, "jwt_auth ($issuer)", true);
    }
});
