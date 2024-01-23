<?php

declare(strict_types=1);

use BookStack\Access\LoginService;
use BookStack\Theming\ThemeEvents;
use BookStack\Facades\Theme;
use BookStack\Users\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final readonly class CloudflareJwtValidator
{
    private static function base64_url_decode(string $str): string|false
    {
        if (($len = strlen($str) % 4) !== 0) $str .= str_repeat('=', 4 - $len);
        return base64_decode(strtr($str, '-_', '+/'), true);
    }

    public function __construct(private string $team, private string $aud)
    {
    }

    public function verify(string $jwt): array|false
    {
        $tokens = explode('.', $jwt);
        if (count($tokens) !== 3) return false;

        $header = json_decode(self::base64_url_decode($tokens[0]), true);
        if (!is_array($header)) return false;
        $payload = json_decode(self::base64_url_decode($tokens[1]), true);
        if (!is_array($payload)) return false;
        $signature = self::base64_url_decode($tokens[2]);
        if ($signature === false) return false;

        if (!in_array($this->aud, $payload['aud'])) return false;
        // TODO: Check (nbf, iat, exp)

        $result = openssl_verify("$tokens[0].$tokens[1]", $signature, $this->findKey($header['kid']), OPENSSL_ALGO_SHA256);
        if ($result !== 1) return false;

        return $payload;
    }

    private function findKey(string $kid): ?string
    {
        $domain = "$this->team.cloudflareaccess.com";
        $key = Cache::get("$domain/$kid");
        if (is_null($key)) {
            $certs = json_decode(file_get_contents("https://$domain/cdn-cgi/access/certs"), true);
            foreach ($certs['public_certs'] as $pair) {
                Cache::put("$domain/{$pair['kid']}", $pair['cert'], 86400 * 7);
                if ($kid === $pair['kid']) $key = $pair['cert'];
            }
        }
        return $key;
    }
}

Theme::listen(ThemeEvents::WEB_MIDDLEWARE_BEFORE, function (Request $request) {
    if (auth()->check()) return;

    $jwt = $request->headers->get('Cf-Access-Jwt-Assertion');
    if (!is_string($jwt)) return;

    $validator = new CloudflareJwtValidator('Your team name', 'Your application AUD');
    if ($payload = $validator->verify($jwt)) {
        if ($user = User::query()->where('email', '=', $payload['email'])->first()) {
            app()->make(LoginService::class)->login($user, 'cloudflare_zero_trust_auth');
        }
    }
});
