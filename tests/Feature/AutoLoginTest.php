<?php

use Goldnead\IdentityContracts\AutoLogin\AutoLogin;
use Goldnead\IdentityContracts\Events\AutoLoginSucceeded;
use Goldnead\IdentityContracts\Mail\AutoLoginRenewedMail;
use Goldnead\IdentityContracts\Tests\Fixtures\AutoLoginUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config([
        'app.url' => 'https://example.test',
        'app.locale' => 'de',
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'auth.providers.users.model' => AutoLoginUser::class,
        'identity-contracts.autologin.enabled' => true,
        'identity-contracts.autologin.paths' => ['/account/'],
    ]);

    URL::forceRootUrl('https://example.test');
    URL::forceScheme('https');

    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password')->nullable();
        $table->string('role')->nullable();
        $table->boolean('super')->default(false);
        $table->rememberToken();
    });

    $this->user = AutoLoginUser::create([
        'name' => 'Lea', 'email' => 'lea@example.test', 'password' => Hash::make('geheim'), 'role' => 'student',
    ]);

    $this->links = app(AutoLogin::class);
    $this->lesson = 'https://example.test/account/courses/cvt/lessons/tag-1';
});

function signed(string $email, string $url): string
{
    $link = app(AutoLogin::class)->linkFor($email, $url);
    expect($link)->not->toBeNull();

    return $link;
}

function path(string $url): string
{
    return $url;
}

it('builds a signed expiring link that logs exactly that user in and lands on the original path', function () {
    $link = signed('lea@example.test', $this->lesson);

    expect($link)->toStartWith('https://example.test/!/autologin');

    $response = $this->get(path($link));

    $response->assertRedirect('/account/courses/cvt/lessons/tag-1');
    expect(Auth::id())->toBe($this->user->id);
});

it('keeps the query string and fragment of the target', function () {
    $link = signed('lea@example.test', 'https://example.test/account/courses/cvt?tab=2#video');

    $this->get(path($link))->assertRedirect('/account/courses/cvt?tab=2#video');
});

it('accepts a relative path as the target', function () {
    $link = signed('LEA@example.test', '/account/courses/cvt');

    $this->get(path($link))->assertRedirect('/account/courses/cvt');
});

it('uses the configured lifetime, 14 days by default', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');
    $link = signed('lea@example.test', $this->lesson);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $q);

    expect((int) $q['expires'])->toBe(Carbon::now()->addDays(14)->getTimestamp());

    config(['identity-contracts.autologin.ttl_days' => 3]);
    $link = signed('lea@example.test', $this->lesson);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $q);

    expect((int) $q['expires'])->toBe(Carbon::now()->addDays(3)->getTimestamp());
    Carbon::setTestNow();
});

it('returns no link when it is switched off, for unknown addresses and for foreign or other pages', function () {
    expect($this->links->linkFor('nobody@example.test', $this->lesson))->toBeNull();
    expect($this->links->linkFor('lea@example.test', 'https://example.test/preise'))->toBeNull();
    expect($this->links->linkFor('lea@example.test', 'https://evil.test/account/x'))->toBeNull();
    expect($this->links->linkFor('lea@example.test', 'https://example.test.evil.test/account/x'))->toBeNull();
    expect($this->links->linkFor('lea@example.test', '/accounting'))->toBeNull();

    config(['identity-contracts.autologin.enabled' => false]);
    expect($this->links->linkFor('lea@example.test', $this->lesson))->toBeNull();
});

it('rewrites only matching internal anchors in html and leaves the rest alone', function () {
    $html = '<p><a class="x" href="'.$this->lesson.'">Lektion</a> <a href="https://example.test/preise">Preise</a>'
        .' <a href="https://evil.test/account/x">Fremd</a> <a href=\'/account/courses/cvt\'>Kurs</a></p>';

    $out = $this->links->rewriteHtml($html, 'lea@example.test');

    expect($out)->toContain('href="https://example.test/!/autologin?')
        ->toContain('href="https://example.test/preise"')
        ->toContain('href="https://evil.test/account/x"')
        ->not->toContain($this->lesson.'"');
    expect(substr_count($out, '/!/autologin?'))->toBe(2);
});

it('rewrites matching urls in a plain text part', function () {
    $out = $this->links->rewriteText("Hier: {$this->lesson}\nPreise: https://example.test/preise", 'lea@example.test');

    expect($out)->toContain('https://example.test/!/autologin?')->toContain('Preise: https://example.test/preise');
});

it('leaves everything untouched for an address without account', function () {
    $html = '<a href="'.$this->lesson.'">x</a>';

    expect($this->links->rewriteHtml($html, 'nobody@example.test'))->toBe($html);
});

// ---- Security --------------------------------------------------------------

it('answers 403 for a tampered signature, a tampered target and a tampered user', function () {
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link).'x')->assertForbidden();

    $other = AutoLoginUser::create(['email' => 'max@example.test', 'password' => Hash::make('x'), 'role' => 'student']);
    $this->get(str_replace('u='.$this->user->id, 'u='.$other->id, path($link)))->assertForbidden();

    $evil = preg_replace('/to=[^&]+/', 'to='.urlencode('/account/other'), path($link));
    $this->get($evil)->assertForbidden();

    $noSig = preg_replace('/&signature=[^&]+/', '', path($link));
    $this->get($noSig)->assertForbidden();

    expect(Auth::check())->toBeFalse();
});

it('still works when a sending platform appends its own parameter, and only then', function () {
    $link = signed('lea@example.test', $this->lesson);

    $this->get($link.'&_se=amVhbkBleGFtcGxl')->assertRedirect('/account/courses/cvt/lessons/tag-1');
    Auth::logout();
    $this->get($link.'&foo=bar')->assertForbidden();
    $this->get(preg_replace('/to=[^&]+/', 'to='.urlencode('/account/other'), $link).'&_se=x')->assertForbidden();
});

it('refuses encoded dot segments when building a link', function () {
    expect($this->links->linkFor('lea@example.test', '/account/%2e%2e/admin'))->toBeNull()
        ->and($this->links->linkFor('lea@example.test', '/account/../admin'))->toBeNull()
        ->and($this->links->linkFor('lea@example.test', '/account/a.b/c'))->not->toBeNull();
});

it('shows a friendly page with a new-link option and does not log in once the link has expired', function () {
    Carbon::setTestNow('2026-10-23 12:00:00');
    $link = signed('lea@example.test', $this->lesson);
    Carbon::setTestNow('2026-11-10 12:00:00');

    $response = $this->get(path($link));

    $response->assertStatus(410)->assertSee('abgelaufen')->assertSee('neuen Link');
    expect(Auth::check())->toBeFalse();
    Carbon::setTestNow();
});

it('mails a fresh link from the expired page, to the address of the account only', function () {
    Mail::fake();
    Carbon::setTestNow('2026-10-23 12:00:00');
    $link = signed('lea@example.test', $this->lesson);
    Carbon::setTestNow('2026-11-10 12:00:00');

    $this->post(path($link), ['action' => 'renew'])->assertOk()->assertSee('geschickt');

    Mail::assertSent(AutoLoginRenewedMail::class, function (AutoLoginRenewedMail $mail) {
        return $mail->hasTo('lea@example.test') && str_contains($mail->url, '/!/autologin?');
    });
    expect(Auth::check())->toBeFalse();
    Carbon::setTestNow();
});

it('rate limits the renew mail', function () {
    Mail::fake();
    Carbon::setTestNow('2026-10-23 12:00:00');
    $link = signed('lea@example.test', $this->lesson);
    Carbon::setTestNow('2026-11-10 12:00:00');

    foreach (range(1, 3) as $i) {
        $this->post(path($link), ['action' => 'renew'])->assertOk();
    }
    $this->post(path($link), ['action' => 'renew'])->assertStatus(429);
    Mail::assertSent(AutoLoginRenewedMail::class, 3);
    Carbon::setTestNow();
});

it('does not renew a link whose signature is wrong', function () {
    Mail::fake();
    $link = signed('lea@example.test', $this->lesson);

    $this->post(path($link).'x', ['action' => 'renew'])->assertForbidden();
    Mail::assertNothingSent();
});

it('refuses a target outside the configured paths even with a correct signature', function () {
    $link = signed('lea@example.test', $this->lesson);
    config(['identity-contracts.autologin.paths' => ['/kurse/']]);

    $this->get(path($link))->assertForbidden();
    expect(Auth::check())->toBeFalse();
});

it('never redirects to an external or scheme-relative target', function (string $target) {
    $signedUrl = URL::temporarySignedRoute('identity.autologin', now()->addDay(), [
        'u' => $this->user->id,
        'b' => $this->links->binding($this->user, 'lea@example.test'),
        'to' => $target,
    ]);

    $this->get(path($signedUrl))->assertForbidden();
    expect(Auth::check())->toBeFalse();
})->with([
    'absolute' => 'https://evil.test/account/x',
    'scheme relative' => '//evil.test/account/x',
    'backslash' => '/\\evil.test/account/x',
    'backslash inside' => '/account/\\..\\x',
    'javascript' => 'javascript:alert(1)',
    'newline' => "/account/x\r\nLocation: https://evil.test",
    'no leading slash' => 'account/x',
    'dot segments' => '/account/../admin',
    'encoded dot segments' => '/account/%2e%2e/admin',
    'mixed encoded dots' => '/account/.%2E/x',
    'encoded slash' => '/account%2f..%2fadmin',
    'encoded backslash' => '/account/%5cevil',
    'encoded null' => '/account/%00',
]);

it('does not auto login a user with a super flag, a disallowed role or cp access', function () {
    $super = AutoLoginUser::create(['email' => 'boss@example.test', 'password' => Hash::make('x'), 'role' => 'student', 'super' => true]);
    $admin = AutoLoginUser::create(['email' => 'admin@example.test', 'password' => Hash::make('x'), 'role' => 'admin']);

    config(['identity-contracts.autologin.allowed_roles' => ['student', 'lead', '']]);

    expect($this->links->linkFor('boss@example.test', $this->lesson))->toBeNull()
        ->and($this->links->linkFor('admin@example.test', $this->lesson))->toBeNull()
        ->and($this->links->linkFor('lea@example.test', $this->lesson))->not->toBeNull();

    // A link issued earlier is refused too: the check runs again on use.
    $link = signed('lea@example.test', $this->lesson);
    $this->user->forceFill(['role' => 'admin'])->save();
    $this->get(path($link))->assertForbidden();
    expect(Auth::check())->toBeFalse();
});

it('lets the host veto a user through the eligibility callback', function () {
    config(['identity-contracts.autologin.eligible' => fn ($user) => $user->email !== 'lea@example.test']);

    expect($this->links->linkFor('lea@example.test', $this->lesson))->toBeNull();
});

it('invalidates the link when the password changes', function () {
    $link = signed('lea@example.test', $this->lesson);
    $this->user->forceFill(['password' => Hash::make('neu')])->save();

    $this->get(path($link))->assertStatus(410);
    expect(Auth::check())->toBeFalse();
});

it('invalidates the link when the remember token changes', function () {
    $link = signed('lea@example.test', $this->lesson);
    $this->user->forceFill(['remember_token' => 'rotated'])->save();

    $this->get(path($link))->assertStatus(410);
    expect(Auth::check())->toBeFalse();
});

it('invalidates the link when the email address of the account changes', function () {
    $link = signed('lea@example.test', $this->lesson);
    $this->user->forceFill(['email' => 'lea.neu@example.test'])->save();

    $this->get(path($link))->assertStatus(410);
    expect(Auth::check())->toBeFalse();
});

it('does not renew a link that was invalidated by a password change', function () {
    Mail::fake();
    Carbon::setTestNow('2026-10-23 12:00:00');
    $link = signed('lea@example.test', $this->lesson);
    $this->user->forceFill(['password' => Hash::make('neu')])->save();
    Carbon::setTestNow('2026-11-10 12:00:00');

    $this->post(path($link), ['action' => 'renew']);
    Mail::assertNothingSent();
    Carbon::setTestNow();
});

it('keeps the link usable for repeated clicks until it expires', function () {
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link))->assertRedirect();
    Auth::logout();
    $this->get(path($link))->assertRedirect('/account/courses/cvt/lessons/tag-1');
    expect(Auth::id())->toBe($this->user->id);
});

it('just shows the target when the same user is already logged in', function () {
    $this->actingAs($this->user);
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link))->assertRedirect('/account/courses/cvt/lessons/tag-1');
    expect(Auth::id())->toBe($this->user->id);
});

it('does not silently switch accounts: asks first, switches only on a confirmed post', function () {
    $other = AutoLoginUser::create(['email' => 'max@example.test', 'password' => Hash::make('x'), 'role' => 'student']);
    $this->actingAs($other);
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link))->assertOk()->assertSee('max@example.test')->assertSee('wechseln');
    expect(Auth::id())->toBe($other->id);

    $this->post(path($link), ['action' => 'switch'])->assertRedirect('/account/courses/cvt/lessons/tag-1');
    expect(Auth::id())->toBe($this->user->id);
});

it('regenerates the session id on login', function () {
    $link = signed('lea@example.test', $this->lesson);
    $this->startSession();
    $before = session()->getId();

    $this->get(path($link));

    expect(session()->getId())->not->toBe($before);
});

it('marks the address as verified on the first login when the host wants that', function () {
    config(['identity-contracts.autologin.verify_email' => true]);
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link));

    expect($this->user->fresh()->email_verified_at)->not->toBeNull();
});

it('leaves verification alone by default', function () {
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link));

    expect($this->user->fresh()->email_verified_at)->toBeNull();
});

it('does not touch an address that was verified already', function () {
    config(['identity-contracts.autologin.verify_email' => true]);
    Carbon::setTestNow('2026-01-01 00:00:00');
    $this->user->forceFill(['email_verified_at' => now()])->save();
    Carbon::setTestNow('2026-10-23 00:00:00');
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link));

    expect($this->user->fresh()->email_verified_at->toDateString())->toBe('2026-01-01');
    Carbon::setTestNow();
});

it('announces a successful login without carrying the link', function () {
    Event::fake([AutoLoginSucceeded::class]);
    $link = signed('lea@example.test', $this->lesson);

    $this->get(path($link));

    Event::assertDispatched(AutoLoginSucceeded::class, fn ($e) => $e->user->is($this->user) && $e->path === '/account/courses/cvt/lessons/tag-1');
});

it('never writes the signature to the log, not for valid, expired or tampered links', function () {
    Log::spy();
    $link = signed('lea@example.test', $this->lesson);
    parse_str((string) parse_url($link, PHP_URL_QUERY), $q);

    $this->get(path($link));
    $this->get(path($link).'x');
    $this->get(path($link))->assertRedirect();

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
    expect($q['signature'])->not->toBe('');
});

it('sends the redirect without caching and without a referrer', function () {
    $link = signed('lea@example.test', $this->lesson);

    $response = $this->get(path($link));

    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('rate limits the endpoint', function () {
    config(['identity-contracts.autologin.rate_limit' => 3]);
    RateLimiter::clear('identity-autologin');

    foreach (range(1, 3) as $i) {
        $this->get('/!/autologin?x='.$i)->assertForbidden();
    }
    $this->get('/!/autologin?x=4')->assertStatus(429);
});

it('answers 404 on the route when switched off', function () {
    config(['identity-contracts.autologin.enabled' => false]);

    $this->get('/!/autologin')->assertNotFound();
});
