<?php

namespace App\Support;

use App\Exceptions\RecordHidden;
use App\Models\AuditLog;
use App\Models\Scopes\CountryScope;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Day 3: auditdeny. A signed-in user refused by the server is written to
 * the audit log as access_refused, so the log holds what was attempted and
 * not only what was done.
 *
 * Three cases are recorded:
 * - a 403 from a policy, a gate, a "can" middleware or an abort(403);
 * - a 404 that hides another member state's record: the country scope made
 *   the route's record unfindable, although the record exists;
 * - a 404 a controller raises on purpose (RecordHidden) for a record the
 *   reader may not open, so the answer does not confirm it exists.
 *
 * The person sees the same 403 or 404 page as before; nothing on the page
 * says the attempt was written down. The entry holds the route name, the
 * path (route parameters that could be secrets masked, never the query
 * string), the method, the ability refused, the reason and the status, plus
 * the record type and id when known. Never a request body, a password or a
 * token. Guests are not recorded: failed sign-ins are already logged.
 *
 * At most one entry per user, route and target per minute (the cache), so
 * a refresh loop cannot flood the log. The row goes through
 * AuditLog::record, so the hash chain and its head apply.
 */
final class AccessRefusals
{
    /** A role or permission refused (403). */
    public const FORBIDDEN = 'forbidden';

    /** Another member state's record, answered as not found (404). */
    public const OTHER_MEMBER_STATE = 'other_member_state';

    /** A record the reader is not cleared to open, answered as not found (404). */
    public const NOT_CLEARED = 'not_cleared';

    public const REASONS = [self::FORBIDDEN, self::OTHER_MEMBER_STATE, self::NOT_CLEARED];

    /** Seconds during which the same user, route and target is written once. */
    public const WINDOW = 60;

    /** Route parameters never written as they are: they can be secrets. */
    private const SECRET_PARAMETER = '/token|secret|hash|signature|code|key|password|email/i';

    /** The request attribute that holds the last ability a gate refused. */
    private const LAST_DENIAL = 'auditdeny.denial';

    /**
     * Remember the last ability a gate check refused on this request, with
     * its record, so the 403 that follows can name what was refused. An
     * allowed check clears it: an exception thrown by hand after an allowed
     * check names no ability rather than the wrong one.
     */
    public static function listen(): void
    {
        Gate::after(function ($user, string $ability, $result, array $arguments = []): void {
            $request = request();
            $denied = $result === false || $result === null || ($result instanceof Response && $result->denied());

            if (! $denied) {
                $request->attributes->remove(self::LAST_DENIAL);

                return;
            }

            $target = $arguments[0] ?? null;
            $request->attributes->set(self::LAST_DENIAL, [
                'ability' => $ability,
                'type' => match (true) {
                    $target instanceof Model => $target->getMorphClass(),
                    is_string($target) && is_subclass_of($target, Model::class) => (new $target)->getMorphClass(),
                    default => null,
                },
                'id' => $target instanceof Model && $target->exists ? $target->getKey() : null,
            ]);
        });
    }

    /**
     * Called while an HTTP exception is rendered. Records the refusal when
     * it is one, and never lets the recording turn a 403 or 404 into a 500.
     */
    public static function capture(HttpException $e, Request $request): void
    {
        try {
            $user = $request->user();
            if (! $user instanceof User) {
                return;
            }

            $refusal = self::classify($e, $request);
            if ($refusal !== null) {
                self::record($request, $user, $e->getStatusCode(), ...$refusal);
            }
        } catch (Throwable $failure) {
            report($failure);
        }
    }

    /**
     * What a rendered exception refused, or null when it is not a refusal
     * (a page that does not exist, an expired signed link, a bad origin).
     *
     * @return array{0: string, 1: ?string, 2: ?string, 3: int|string|null}|null  reason, ability, type, id
     */
    private static function classify(HttpException $e, Request $request): ?array
    {
        $previous = $e->getPrevious();

        if ($e instanceof RecordHidden) {
            return [$e->reason, $e->ability, $e->subject->getMorphClass(), $e->subject->getKey()];
        }

        if ($e->getStatusCode() === 403) {
            if ($e instanceof InvalidSignatureException || ($previous !== null && ! $previous instanceof AuthorizationException)) {
                return null;
            }

            $denial = $previous instanceof AuthorizationException ? $request->attributes->get(self::LAST_DENIAL) : null;

            return [self::FORBIDDEN, $denial['ability'] ?? null, $denial['type'] ?? null, $denial['id'] ?? null];
        }

        if ($e->getStatusCode() === 404 && $previous instanceof AuthorizationException) {
            // A policy that answers denyAsNotFound().
            $denial = $request->attributes->get(self::LAST_DENIAL);

            return [self::NOT_CLEARED, $denial['ability'] ?? null, $denial['type'] ?? null, $denial['id'] ?? null];
        }

        if ($e->getStatusCode() === 404 && $previous instanceof ModelNotFoundException) {
            return self::hiddenByCountry($previous);
        }

        return null;
    }

    /**
     * A record the country scope hid: it exists once the scope is lifted.
     * Only ids in digits are looked up, so a slug is never read as a key.
     *
     * @return array{0: string, 1: null, 2: string, 3: int}|null
     */
    private static function hiddenByCountry(ModelNotFoundException $e): ?array
    {
        $class = $e->getModel();
        $ids = array_values(array_filter((array) $e->getIds(), fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))));

        if (! is_string($class) || ! is_subclass_of($class, Model::class) || count($ids) !== 1) {
            return null;
        }

        $model = new $class;
        if (! $class::hasGlobalScope(CountryScope::class)) {
            return null;
        }

        $exists = $class::query()->withoutGlobalScope(CountryScope::class)->whereKey((int) $ids[0])->exists();

        return $exists ? [self::OTHER_MEMBER_STATE, null, $model->getMorphClass(), (int) $ids[0]] : null;
    }

    /** Write the entry, once per user, route and target in the window. */
    private static function record(Request $request, User $user, int $status, string $reason, ?string $ability, ?string $type, int|string|null $id): void
    {
        $route = $request->route()?->getName();
        $path = self::path($request);
        $target = $type !== null ? $type.'#'.$id : $path;

        if (! Cache::add('audit-refused:'.sha1($user->getKey().'|'.($route ?? $path).'|'.$target), 1, self::WINDOW)) {
            return;
        }

        // The record refused, by type and id; by type alone for a check on
        // a class (viewAny, create). The model is built, never loaded: the
        // refused person's request reads nothing more from it.
        $subject = null;
        if ($type !== null) {
            $class = Relation::getMorphedModel($type) ?? $type;
            if (is_subclass_of($class, Model::class)) {
                $subject = new $class;
                if ($id !== null) {
                    $subject->setAttribute($subject->getKeyName(), $id);
                }
            }
        }

        AuditLog::record(AuditLog::ACCESS_REFUSED, $subject, array_filter([
            'reason' => $reason,
            'status' => $status,
            'method' => $request->method(),
            'route' => $route,
            'path' => $path,
            'ability' => $ability,
        ], fn ($v) => $v !== null), $user, $request->ip());
    }

    /**
     * The path asked for, without its query string, with each route
     * parameter that could be a secret (a share token, a reset code, an
     * address) or that is not a plain id or slug replaced by its name.
     */
    public static function path(Request $request): string
    {
        $route = $request->route();

        if ($route === null || ! method_exists($route, 'uri')) {
            return '/'.ltrim($request->path(), '/');
        }

        $parameters = $route->originalParameters();

        $path = preg_replace_callback('/\{(\w+)\??\}/', function (array $m) use ($parameters): string {
            $name = $m[1];
            $value = $parameters[$name] ?? null;

            if ($value === null) {
                return '';
            }

            if (! is_string($value) || preg_match(self::SECRET_PARAMETER, $name) || ! preg_match('/^[A-Za-z0-9._-]{1,64}$/', $value)) {
                return '{'.$name.'}';
            }

            return $value;
        }, $route->uri());

        return '/'.trim(preg_replace('#/+#', '/', (string) $path), '/');
    }
}
