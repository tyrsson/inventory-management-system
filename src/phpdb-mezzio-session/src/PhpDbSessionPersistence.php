<?php

declare(strict_types=1);


namespace PhpDb\Session;

use Mezzio\Session\InitializePersistenceIdInterface;
use Mezzio\Session\Persistence\CacheHeadersGeneratorTrait;
use Mezzio\Session\Persistence\SessionCookieAwareTrait;
use Mezzio\Session\Session;
use Mezzio\Session\SessionCookiePersistenceInterface;
use Mezzio\Session\SessionIdentifierAwareInterface;
use Mezzio\Session\SessionInterface;
use Mezzio\Session\SessionPersistenceInterface;
use PhpDb\Adapter\AdapterInterface;
use PhpDb\Session\Sql\Insert;
use PhpDb\Sql\Sql;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function bin2hex;
use function date;
use function ini_get;
use function random_bytes;
use function serialize;
use function time;
use function unserialize;

/**
 * Async-safe PhpDb-backed session persistence.
 *
 * Does not use ext-session or any process-global state. Session identity is
 * retrieved via SessionIdentifierAwareInterface::getId() (1.x; moves to
 * SessionInterface in 2.0), making this implementation safe
 * for use in concurrent async environments
 * (TrueAsync, Swoole, ReactPHP).
 *
 * Payload is serialized using PHP's serialize()/unserialize(). Objects stored
 * in session data must implement __serialize()/__unserialize() for correct
 * round-tripping.
 */
final class PhpDbSessionPersistence implements
    InitializePersistenceIdInterface,
    SessionPersistenceInterface
{
    use CacheHeadersGeneratorTrait;
    use SessionCookieAwareTrait;

    private readonly Sql $sql;

    public function __construct(AdapterInterface $adapter)
    {
        $this->sql          = new Sql($adapter, 'session');
        $this->cacheLimiter = ini_get('session.cache_limiter') ?: 'nocache';
        $this->cacheExpire  = (int) ini_get('session.cache_expire');
        $this->cookieName   = ini_get('session.name') ?: 'PHPSESSID';
        $this->cookiePath   = ini_get('session.cookie_path') ?: '/';
    }

    #[\Override]
    public function initializeSessionFromRequest(ServerRequestInterface $request): SessionInterface
    {
        $id = $this->getSessionCookieValueFromRequest($request);

        if ($id === '') {
            return new Session([], '');
        }

        $select = $this->sql->select()
            ->columns(['payload'])
            ->where(['id' => $id]);
        $select->where->greaterThan('expires_at', date('Y-m-d H:i:s'));

        $result = $this->sql->prepareStatementForSqlObject($select)->execute();
        $row    = $result->current();

        if ($row === false || $row === null) {
            // Session not found or expired — return empty session keeping same ID.
            // Browser already holds the cookie; on write it will upsert.
            return new Session([], $id);
        }

        $data = unserialize((string) $row['payload'], ['allowed_classes' => true]);

        return new Session($data !== false ? $data : [], $id);
    }

    #[\Override]
    public function persistSession(SessionInterface $session, ResponseInterface $response): ResponseInterface
    {
        // Retrieve the session ID — uses SessionIdentifierAwareInterface (1.x);
        // getId() moves to SessionInterface in 2.0.
        $id = $session instanceof SessionIdentifierAwareInterface
            ? $session->getId()
            : '';

        // Regenerate: either explicitly requested, or new session with data.
        if ($session->isRegenerated() || ($id === '' && $session->hasChanged())) {
            if ($id !== '' && $session->isRegenerated()) {
                $this->destroy($id);
            }

            $id = $this->generateId();
        }

        // No ID means a new session was created but never written to.
        if ($id === '') {
            return $response;
        }

        // Unchanged sessions do not need a new write or cookie.
        if (! $session->hasChanged()) {
            return $response;
        }

        $ttl = $session instanceof SessionCookiePersistenceInterface && $session->getSessionLifetime() > 0
            ? $session->getSessionLifetime()
            : (int) ini_get('session.gc_maxlifetime');

        $expiresAt = date('Y-m-d H:i:s', time() + $ttl);
        $now       = date('Y-m-d H:i:s');
        $payload   = serialize($session->toArray());

        $this->sql->prepareStatementForSqlObject(
            (new Insert('session'))
                ->values([
                    'id'          => $id,
                    'payload'     => $payload,
                    'expires_at'  => $expiresAt,
                    'modified_at' => $now,
                ])
        )->execute();

        $response = $this->addSessionCookieHeaderToResponse($response, $id, $session);
        $response = $this->addCacheHeadersToResponse($response);

        return $response;
    }

    #[\Override]
    public function initializeId(SessionInterface $session): SessionInterface
    {
        $id = $session instanceof SessionIdentifierAwareInterface
            ? $session->getId()
            : '';

        if ($id !== '' && ! $session->isRegenerated()) {
            return $session;
        }

        return new Session($session->toArray(), $this->generateId());
    }

    private function destroy(string $id): void
    {
        $delete = $this->sql->delete()->where(['id' => $id]);
        $this->sql->prepareStatementForSqlObject($delete)->execute();
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
