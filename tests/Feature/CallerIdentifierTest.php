<?php

namespace Jarryd\Sunset\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Jarryd\Sunset\Sunset;
use Jarryd\Sunset\Support\CallerIdentifier;
use Jarryd\Sunset\Tests\Stubs\TokenUser;
use Jarryd\Sunset\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CallerIdentifierTest extends TestCase
{
    protected function tearDown(): void
    {
        Sunset::identifyCallersUsing(null);

        parent::tearDown();
    }

    private function request(?object $user = null, ?string $userAgent = null): Request
    {
        $request = Request::create('/', server: $userAgent === null ? [] : ['HTTP_USER_AGENT' => $userAgent]);
        $request->setUserResolver(fn () => $user);

        // Request::create() fills in "Symfony" when no user agent is given.
        if ($userAgent === null) {
            $request->headers->remove('User-Agent');
        }

        return $request;
    }

    private function user(int $id, ?object $token = null): TokenUser
    {
        $user = new TokenUser;
        $user->forceFill(['id' => $id]);
        $user->token = $token;

        return $user;
    }

    #[Test]
    public function an_api_token_name_comes_first()
    {
        $request = $this->request($this->user(42, (object) ['name' => 'mobile-app']), 'okhttp/4.9.3');

        $this->assertSame('token:mobile-app', CallerIdentifier::identify($request));
    }

    #[Test]
    public function tokens_without_a_name_fall_back_to_the_user()
    {
        // Like Sanctum's TransientToken, used for cookie-based SPA requests.
        $this->assertSame('user:42', CallerIdentifier::identify($this->request($this->user(42, new \stdClass))));
        $this->assertSame('user:42', CallerIdentifier::identify($this->request($this->user(42))));
    }

    #[Test]
    public function users_without_tokens_are_identified_by_id()
    {
        $user = new User;
        $user->forceFill(['id' => 7]);

        $this->assertSame('user:7', CallerIdentifier::identify($this->request($user, 'Mozilla/5.0')));
    }

    #[Test]
    #[DataProvider('userAgents')]
    public function guests_are_identified_by_user_agent_family(?string $userAgent, string $expected)
    {
        $this->assertSame($expected, CallerIdentifier::identify($this->request(null, $userAgent)));
    }

    public static function userAgents(): array
    {
        return [
            'okhttp' => ['okhttp/4.9.3', 'agent:okhttp'],
            'python' => ['python-requests/2.31.0', 'agent:python-requests'],
            'curl' => ['curl/8.5.0', 'agent:curl'],
            'guzzle' => ['GuzzleHttp/7', 'agent:guzzlehttp'],
            'no version' => ['MyIntegration', 'agent:myintegration'],
            'browser' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15', 'agent:browser'],
            'bot' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'agent:bot'],
            'empty' => ['', 'anonymous'],
            'missing' => [null, 'anonymous'],
        ];
    }

    #[Test]
    public function callers_can_be_identified_your_own_way()
    {
        Sunset::identifyCallersUsing(fn (Request $request) => 'tenant:'.$request->header('X-Tenant'));

        $request = $this->request($this->user(42, (object) ['name' => 'mobile-app']));
        $request->headers->set('X-Tenant', 'acme');

        $this->assertSame('tenant:acme', CallerIdentifier::identify($request));
    }

    #[Test]
    public function a_custom_identifier_returning_null_means_anonymous()
    {
        Sunset::identifyCallersUsing(fn () => null);

        $this->assertSame('anonymous', CallerIdentifier::identify($this->request($this->user(42))));
    }

    #[Test]
    public function the_default_can_be_restored()
    {
        Sunset::identifyCallersUsing(fn () => 'custom');
        Sunset::identifyCallersUsing(null);

        $this->assertSame('user:42', CallerIdentifier::identify($this->request($this->user(42))));
    }

    #[Test]
    public function callers_are_limited_to_255_characters()
    {
        Sunset::identifyCallersUsing(fn () => Str::repeat('x', 300));

        $this->assertSame(255, strlen(CallerIdentifier::identify($this->request())));
    }
}
