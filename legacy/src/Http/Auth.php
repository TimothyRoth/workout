<?php

namespace App\Http;

use App\Database;

/**
 * Login on top of a PHP session (cookie: HttpOnly, SameSite=Lax, Secure on
 * HTTPS). "Angemeldet bleiben" sets a second cookie holding a random
 * selector (24 hex) followed by a validator (64 hex); only the validator's
 * hash is stored, and the token is rotated on every use. Users are created with bin/console.
 */
class Auth
{
    public const SESSION_COOKIE = 'workout_session';
    public const REMEMBER_COOKIE = 'workout_remember';
    public const REMEMBER_DAYS = 30;

    private const SESSION_USER = 'user_id';
    private const SESSION_TARGET = 'login_target';
    private const SESSION_FLASH = 'login_flash';

    private static ?array $user = null;
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started) {
            return;
        }
        self::$started = true;

        session_name(self::SESSION_COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (!isset($_SESSION[self::SESSION_USER])) {
            self::loginFromRememberCookie();
        }
    }

    /** The logged-in user (id, username) or null. */
    public static function user(): ?array
    {
        self::start();
        if (self::$user !== null) {
            return self::$user;
        }
        $id = $_SESSION[self::SESSION_USER] ?? null;
        if (!is_int($id)) {
            return null;
        }
        $user = Database::findUser($id);
        if ($user === null) { // deleted meanwhile
            unset($_SESSION[self::SESSION_USER]);
            return null;
        }
        return self::$user = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Verify the credentials and open the session. Returns false on failure
     * (after a short delay, so guessing stays slow).
     */
    public static function attempt(string $username, string $password, bool $remember): bool
    {
        self::start();
        $user = $username !== '' ? Database::findUserByUsername($username) : null;

        if ($user === null || !password_verify($password, $user['password_hash'])) {
            usleep(random_int(300_000, 600_000));
            return false;
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Database::updateUserPassword((int)$user['id'], password_hash($password, PASSWORD_DEFAULT));
        }

        self::openSession((int)$user['id']);
        if ($remember) {
            self::issueRememberToken((int)$user['id']);
        }
        return true;
    }

    public static function logout(): void
    {
        self::start();

        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if (strlen($cookie) === 88) {
            Database::deleteAuthToken(substr($cookie, 0, 24));
        }
        self::sendCookie(self::REMEMBER_COOKIE, '', time() - 3600);

        $_SESSION = [];
        self::$user = null;
        session_destroy();
        self::sendCookie(self::SESSION_COOKIE, '', time() - 3600);
    }

    /** Remember where an anonymous visitor wanted to go, for after the login. */
    public static function rememberTarget(string $uri): void
    {
        self::start();
        if (str_starts_with($uri, '/') && !str_starts_with($uri, '//') && $uri !== '/login') {
            $_SESSION[self::SESSION_TARGET] = $uri;
        }
    }

    public static function pullTarget(): string
    {
        self::start();
        $target = $_SESSION[self::SESSION_TARGET] ?? '/';
        unset($_SESSION[self::SESSION_TARGET]);
        return is_string($target) && str_starts_with($target, '/') && !str_starts_with($target, '//') ? $target : '/';
    }

    /** One-shot error + last username for the login form (post/redirect/get). */
    public static function flash(string $error, string $username): void
    {
        self::start();
        $_SESSION[self::SESSION_FLASH] = ['error' => $error, 'username' => $username];
    }

    public static function pullFlash(): array
    {
        self::start();
        $flash = $_SESSION[self::SESSION_FLASH] ?? ['error' => '', 'username' => ''];
        unset($_SESSION[self::SESSION_FLASH]);
        return $flash;
    }

    // ---------------------------------------------------------------

    private static function openSession(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER] = $userId;
        self::$user = null;
    }

    private static function issueRememberToken(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires = time() + self::REMEMBER_DAYS * 86400;

        Database::createAuthToken($selector, $userId, hash('sha256', $validator), gmdate('Y-m-d H:i:s', $expires));
        self::sendCookie(self::REMEMBER_COOKIE, $selector . $validator, $expires);
    }

    private static function loginFromRememberCookie(): void
    {
        $cookie = $_COOKIE[self::REMEMBER_COOKIE] ?? '';
        if ($cookie === '' || !preg_match('/^([0-9a-f]{24})([0-9a-f]{64})$/', $cookie, $m)) {
            return;
        }
        [, $selector, $validator] = $m;

        $token = Database::findAuthToken($selector);
        if ($token === null || !hash_equals($token['validator_hash'], hash('sha256', $validator))) {
            self::sendCookie(self::REMEMBER_COOKIE, '', time() - 3600);
            return;
        }

        // Single use: rotate the token so a stolen cookie only works until the owner's next visit.
        Database::deleteAuthToken($selector);
        self::openSession((int)$token['user_id']);
        self::issueRememberToken((int)$token['user_id']);
    }

    private static function sendCookie(string $name, string $value, int $expires): void
    {
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}
