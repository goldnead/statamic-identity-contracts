<?php

namespace Goldnead\IdentityContracts\AutoLogin;

use Goldnead\IdentityContracts\Support\EmailShape;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Statamic\Facades\User;

/**
 * Turns links in a mail into signed, expiring "log me in" links.
 *
 * A reader who got a mail with a link to a page behind the login lands on the
 * login form, with a password nobody ever told them. This class is the one
 * place that decides which links get a login in front of them, builds the
 * signed address, and judges who may be logged in that way at all.
 *
 * **Who calls it.** Whatever renders a mail for one known recipient: marketing
 * for a campaign or an automation mail, lead magnets for the delivery mail. They
 * hand over the HTML (or text) and the recipient's address and get it back with
 * the matching links replaced. They do this *before* click tracking wraps the
 * link, so the tracker counts the click and then forwards to the login link.
 *
 * **What a link carries.** The account (`u`), the internal path to land on
 * (`to`), and a binding hash (`b`), all under the framework's URL signature with
 * an expiry. The binding is a keyed hash of the account's id, address, password
 * hash and remember token: change the password (or the address, or sign out of a
 * remembered session) and every link issued earlier stops working. Nothing is
 * stored, so a link can be clicked again until it expires. That is deliberate:
 * a mail scanner that opens the link first must not use it up.
 *
 * **Where it can lead.** Only to an internal path that starts with one of the
 * configured prefixes. The target is checked when the link is built and again
 * when it is used.
 *
 * **Who may be logged in.** Never a user with access to the control panel or a
 * super flag, whatever the host configures. On top of that an optional role
 * allowlist and an optional host callback.
 */
class AutoLogin
{
    public const ROUTE = 'identity.autologin';

    public function enabled(): bool
    {
        return (bool) config('identity-contracts.autologin.enabled', false);
    }

    /**
     * The signed link for one address and one target, or null when this
     * recipient or target gets no login (switched off, no account, not
     * eligible, not an internal matching path). Null means: leave the link as
     * it is.
     */
    public function linkFor(string $email, string $url): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $user = $this->userForEmail($email);

        return $user === null ? null : $this->linkForUser($user, $url);
    }

    public function linkForUser(Authenticatable $user, string $url): ?string
    {
        if (! $this->enabled() || ! $this->eligible($user)) {
            return null;
        }

        $path = $this->internalPath($url);

        if ($path === null) {
            return null;
        }

        // A host whose route cache predates this package has no such route. The
        // mail goes out with its plain link rather than not at all.
        if (! Route::has(self::ROUTE)) {
            return null;
        }

        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addDays(max(1, (int) config('identity-contracts.autologin.ttl_days', 14))),
            [
                'u' => $user->getAuthIdentifier(),
                'b' => $this->binding($user, $this->emailOf($user)),
                'to' => $path,
            ],
        );
    }

    /**
     * Every matching internal anchor in the HTML replaced by its login link.
     * The rest of the HTML is returned byte for byte.
     */
    public function rewriteHtml(string $html, string $email): string
    {
        $user = $this->enabled() ? $this->userForEmail($email) : null;

        if ($user === null || ! $this->eligible($user)) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/(<a\b[^>]*?(?<![\w-])href\s*=\s*)(["\'])(.*?)\2/is',
            function (array $m) use ($user): string {
                $link = $this->linkForUser($user, html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5));

                return $link === null ? $m[0] : $m[1].$m[2].htmlspecialchars($link, ENT_QUOTES | ENT_HTML5).$m[2];
            },
            $html,
        );
    }

    /** The same for the plain text part: bare URLs. */
    public function rewriteText(string $text, string $email): string
    {
        $user = $this->enabled() ? $this->userForEmail($email) : null;

        if ($user === null || ! $this->eligible($user)) {
            return $text;
        }

        return (string) preg_replace_callback(
            '~https?://[^\s<>"\']+~i',
            function (array $m) use ($user): string {
                // Sentence punctuation after a URL is not part of it.
                $url = rtrim($m[0], '.,;:!?)]');
                $tail = substr($m[0], strlen($url));
                $link = $this->linkForUser($user, $url);

                return $link === null ? $m[0] : $link.$tail;
            },
            $text,
        );
    }

    /**
     * The internal path (with query and fragment) a URL leads to, or null when
     * it is external, not on a configured prefix, or not a clean path.
     *
     * Strict on purpose: a path that browsers would read differently from the
     * way PHP does (a backslash, a control character, a second slash) is
     * refused rather than repaired.
     */
    public function internalPath(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            $relative = $url;
        } else {
            $parts = parse_url($url);

            if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
                || isset($parts['user']) || isset($parts['pass'])
                || ! in_array(strtolower($parts['host'] ?? ''), $this->hosts(), true)) {
                return null;
            }

            $relative = ($parts['path'] ?? '/')
                .(isset($parts['query']) ? '?'.$parts['query'] : '')
                .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        }

        if (str_starts_with($relative, '//') || ! str_starts_with($relative, '/')) {
            return null;
        }

        $path = (string) parse_url($relative, PHP_URL_PATH);

        // Dot segments, literal or percent-encoded, would let a browser walk out
        // of the prefix; so would an encoded slash, backslash or control character.
        if ($path === ''
            || preg_match('~(^|/)\.\.?(/|$)~', $path) === 1
            || preg_match('/%(2e|2f|5c|[01][0-9a-f]|7f)/i', $path) === 1) {
            return null;
        }

        foreach ((array) config('identity-contracts.autologin.paths', []) as $prefix) {
            $prefix = rtrim((string) $prefix, '/');

            if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix.'/'))) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * Query parameters a sending platform may add without invalidating a link.
     *
     * @return list<string>
     */
    public function ignoredParameters(): array
    {
        $ignored = array_map('strval', (array) config('identity-contracts.autologin.ignore_parameters', ['_se']));

        return array_values(array_diff($ignored, ['u', 'b', 'to', 'expires', 'signature']));
    }

    /**
     * Hosts that count as "ours".
     *
     * @return list<string>
     */
    protected function hosts(): array
    {
        $hosts = array_map('strtolower', array_map('strval', (array) config('identity-contracts.autologin.hosts', [])));
        $own = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($own) && $own !== '') {
            $hosts[] = strtolower($own);
        }

        return array_values(array_unique($hosts));
    }

    /**
     * The hash that ties a link to the state of the account it was issued for.
     * Changing the password, the address or the remember token changes it.
     */
    public function binding(Authenticatable $user, string $email): string
    {
        $state = json_encode([
            (string) $user->getAuthIdentifier(),
            strtolower(trim($email)),
            (string) $user->getAuthPassword(),
            (string) $user->getRememberToken(),
        ], JSON_THROW_ON_ERROR);

        return substr(hash_hmac('sha256', $state, (string) config('app.key')), 0, 40);
    }

    public function bindingMatches(Authenticatable $user, string $given): bool
    {
        return hash_equals($this->binding($user, $this->emailOf($user)), $given);
    }

    public function emailOf(Authenticatable $user): string
    {
        return strtolower(trim((string) ($user->email ?? '')));
    }

    public function userForEmail(string $email): ?Authenticatable
    {
        $email = strtolower(trim($email));

        if (! EmailShape::plausible($email)) {
            return null;
        }

        $model = $this->userModel();

        if ($model === null) {
            return null;
        }

        $user = $model::query()->whereRaw('lower(email) = ?', [$email])->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    public function userById(mixed $id): ?Authenticatable
    {
        $model = $this->userModel();

        if ($model === null || ! is_scalar($id) || $id === '') {
            return null;
        }

        $user = $model::query()->whereKey($id)->first();

        return $user instanceof Authenticatable ? $user : null;
    }

    /** @return class-string<Model>|null */
    protected function userModel(): ?string
    {
        $guard = config('identity-contracts.autologin.guard') ?: config('auth.defaults.guard');
        $provider = config("auth.guards.{$guard}.provider");
        $model = config("auth.providers.{$provider}.model") ?? config('auth.providers.users.model');

        return is_string($model) && is_a($model, Model::class, true) ? $model : null;
    }

    /**
     * Whether this user may be logged in by a mail link at all. Fails closed:
     * anything this cannot establish counts as "no".
     */
    public function eligible(Authenticatable $user): bool
    {
        try {
            if ($user->getAttribute('super')) {
                return false;
            }

            if ($this->hasControlPanelAccess($user)) {
                return false;
            }

            $roles = config('identity-contracts.autologin.allowed_roles');

            if (is_array($roles)) {
                $role = (string) $user->getAttribute((string) config('identity-contracts.autologin.role_attribute', 'role'));

                if (! in_array($role, array_map('strval', $roles), true)) {
                    return false;
                }
            }

            $veto = config('identity-contracts.autologin.eligible');

            if ($veto !== null) {
                $callable = is_string($veto) && class_exists($veto) ? app($veto) : $veto;

                return is_callable($callable) && (bool) $callable($user);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function hasControlPanelAccess(Authenticatable $user): bool
    {
        if (! class_exists(User::class)) {
            return false;
        }

        $statamic = User::fromUser($user);

        // An account Statamic's user store does not know cannot sign in to the
        // control panel. (The super flag and the role allowlist are checked
        // separately, whatever Statamic says.)
        if ($statamic === null) {
            return false;
        }

        return $statamic->isSuper() || $statamic->hasPermission('access cp');
    }
}
