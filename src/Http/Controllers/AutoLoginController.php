<?php

namespace Goldnead\IdentityContracts\Http\Controllers;

use Goldnead\IdentityContracts\AutoLogin\AutoLogin;
use Goldnead\IdentityContracts\Events\AutoLoginSucceeded;
use Goldnead\IdentityContracts\Mail\AutoLoginRenewedMail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * The landing of an auto login link.
 *
 * One address, two verbs. The GET logs in and redirects, or shows a page when
 * a person has to decide. The POST is sent by those pages' buttons: it
 * confirms switching accounts, or asks for a fresh link. The page posts to the
 * address it was opened on, so the signature keeps holding.
 *
 * What it checks, in this order, and what it answers when a check fails:
 *
 *  1. Switched off: 404. Signature wrong or missing: 403, with nothing said
 *     about why. Nothing below runs for a URL that is not ours.
 *  2. The account is looked up and the binding compared. A password or address
 *     changed since the mail went out: a friendly page, no new link (a new link
 *     would hand a login to whoever holds the old mail).
 *  3. The account must still be eligible (never CP access, never a super user)
 *     and the target must still be an internal path on a configured prefix:
 *     403.
 *  4. Expired: a friendly page that offers a fresh link by mail or the login.
 *  5. Somebody else is logged in: ask first. A link anybody can obtain for their
 *     own account must not log a stranger's browser into it unasked.
 *
 * Nothing here logs the URL, and the response is sent without caching and
 * without a referrer.
 */
class AutoLoginController extends Controller
{
    public function __construct(protected AutoLogin $links) {}

    public function __invoke(Request $request): Response
    {
        abort_unless($this->links->enabled(), 404);
        abort_unless($request->query('expires') !== null && URL::hasCorrectSignature($request, true, $this->links->ignoredParameters()), 403);

        $user = $this->links->userById($request->query('u'));

        abort_if($user === null, 403);

        $binding = is_string($request->query('b')) ? (string) $request->query('b') : '';

        if (! $this->links->bindingMatches($user, $binding)) {
            return $this->page('autologin.outdated', 410);
        }

        abort_unless($this->links->eligible($user), 403);

        $to = $request->query('to');
        $path = is_string($to) ? $this->links->internalPath($to) : null;

        abort_if($path === null, 403);

        $action = $request->isMethod('POST') ? (string) $request->input('action', '') : '';

        if (! URL::signatureHasNotExpired($request)) {
            return $action === 'renew'
                ? $this->renew($user, $path)
                : $this->page('autologin.expired', 410, ['action' => $request->fullUrl()]);
        }

        $guard = Auth::guard(config('identity-contracts.autologin.guard') ?: null);
        $current = $guard->user();

        if ($current !== null && (string) $current->getAuthIdentifier() === (string) $user->getAuthIdentifier()) {
            return $this->redirect($path);
        }

        if ($current !== null) {
            if ($action !== 'switch') {
                return $this->page('autologin.switch', 200, [
                    'action' => $request->fullUrl(),
                    'currentEmail' => (string) ($current->email ?? ''),
                    'targetEmail' => $this->links->emailOf($user),
                ]);
            }

            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $guard->login($user);
        $request->session()->regenerate();

        $this->markVerified($user);

        AutoLoginSucceeded::dispatch($user, $path);

        return $this->redirect($path);
    }

    protected function renew(Authenticatable $user, string $path): Response
    {
        $allowed = RateLimiter::attempt(
            'identity-autologin-renew:'.$user->getAuthIdentifier(),
            max(1, (int) config('identity-contracts.autologin.renew_limit', 3)),
            static fn () => true,
            3600,
        );

        abort_unless($allowed, 429);

        $url = $this->links->linkForUser($user, $path);

        if ($url !== null) {
            Mail::to($this->links->emailOf($user))->send(new AutoLoginRenewedMail($url));
        }

        return $this->page('autologin.renewed', 200);
    }

    protected function markVerified(Authenticatable $user): void
    {
        if (! config('identity-contracts.autologin.verify_email', false)
            || ! method_exists($user, 'getAttributes')
            || ! array_key_exists('email_verified_at', $user->getAttributes())
            || $user->getAttribute('email_verified_at') !== null) {
            return;
        }

        $user->forceFill(['email_verified_at' => now()])->save();
    }

    protected function redirect(string $path): Response
    {
        return $this->guarded(redirect()->to($path));
    }

    /** @param  array<string, mixed>  $data */
    protected function page(string $view, int $status, array $data = []): Response
    {
        return $this->guarded(response()->view('identity-contracts::'.$view, $data + [
            'loginUrl' => (string) config('identity-contracts.autologin.login_url', '/login'),
        ], $status));
    }

    /** No caching of a response to a credential, and no referrer to the next page. */
    protected function guarded(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
