<?php

/**
 * Auth.php
 * ============================================================
 * 役割:
 * - 認証状態管理（ログイン/ログアウト/現在ユーザー取得）
 *
 * 方針（安全側 / 退職者ログイン禁止）:
 * - resigned_on が入っているユーザーはログイン不可
 * - TTL再取得時に resigned_on が入っていたら強制ログアウト（即時反映）
 *
 * 監査:
 * - 成功: login
 * - 失敗: login_failed（reason付き）
 * - 制限: login_blocked（blocked_until等付き）
 *
 * レート制限（IP×email）:
 * - window_seconds 内の失敗回数が max_attempts を超えたら lock_seconds ブロック
 * - 成功時は失敗情報をリセット
 */

class Auth
{
    private const SESSION_KEY = 'auth';
    private const CACHE_TTL_SECONDS = 300;

    public static function check(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['user_id']);
    }

    public static function id(): ?int
    {
        $id = $_SESSION[self::SESSION_KEY]['user_id'] ?? null;
        return $id ? (int)$id : null;
    }

    public static function user(): ?array
    {
        $uid = self::id();
        if (!$uid) return null;

        $cache    = $_SESSION[self::SESSION_KEY]['cache'] ?? null;
        $cachedAt = (int)($_SESSION[self::SESSION_KEY]['cached_at'] ?? 0);

        if (is_array($cache) && (time() - $cachedAt) <= self::CACHE_TTL_SECONDS) {
            if (!empty($cache['resigned_on'])) {
                self::logout();
                return null;
            }
            return $cache;
        }

        $pdo = Db::pdo();
        $st = $pdo->prepare("SELECT * FROM users WHERE id = :id AND resigned_on IS NULL LIMIT 1");
        $st->execute([':id' => $uid]);
        $u = $st->fetch();

        if (!$u) {
            self::logout();
            return null;
        }

        $_SESSION[self::SESSION_KEY]['cache'] = $u;
        $_SESSION[self::SESSION_KEY]['cached_at'] = time();
        return $u;
    }

    private static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') return '';

        $parts = explode('@', $email, 2);
        $local = $parts[0] ?? '';
        $dom   = $parts[1] ?? '';

        if ($local === '') return '***@' . $dom;

        $len = mb_strlen($local, 'UTF-8');
        if ($len === 1) return $local . '***@' . $dom;

        $first = mb_substr($local, 0, 1, 'UTF-8');
        $last  = mb_substr($local, $len - 1, 1, 'UTF-8');
        return $first . '***' . $last . '@' . $dom;
    }

    private static function auditLoginFailed(string $email, string $reason, int $entityId = 0): void
    {
        $diff = [
            'email_masked' => ['old' => null, 'new' => self::maskEmail($email)],
            'reason'       => ['old' => null, 'new' => $reason],
        ];
        Audit::log('users', 'User', $entityId, 'login_failed', null, $diff);
    }

    private static function auditLoginBlocked(string $email, string $blockedUntil, int $failCount, int $entityId = 0): void
    {
        $diff = [
            'email_masked'  => ['old' => null, 'new' => self::maskEmail($email)],
            'reason'        => ['old' => null, 'new' => 'rate_limited'],
            'blocked_until' => ['old' => null, 'new' => $blockedUntil],
            'fail_count'    => ['old' => null, 'new' => (string)$failCount],
        ];
        Audit::log('users', 'User', $entityId, 'login_blocked', null, $diff);
    }

    private static function rateLimitConfig(): array
    {
        $cfg = require __DIR__ . '/config.php';
        $rl = $cfg['auth_rate_limit'] ?? [];
        if (!is_array($rl)) $rl = [];

        return [
            'enabled'        => (bool)($rl['enabled'] ?? true),
            'max_attempts'   => (int)($rl['max_attempts'] ?? 5),
            'window_seconds' => (int)($rl['window_seconds'] ?? 600),
            'lock_seconds'   => (int)($rl['lock_seconds'] ?? 900),
        ];
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = is_string($ip) ? trim($ip) : '';
        return $ip !== '' ? $ip : '0.0.0.0';
    }

    /**
     * ブロック中か判定
     * @return array [blocked(bool), blocked_until(?string), fail_count(int)]
     */
    private static function rateLimitCheck(PDO $pdo, string $ip, string $email): array
    {
        $st = $pdo->prepare("
            SELECT fail_count, blocked_until
            FROM auth_login_throttles
            WHERE ip = :ip AND email = :email
            LIMIT 1
        ");
        $st->execute([':ip' => $ip, ':email' => $email]);
        $row = $st->fetch();

        if (!$row) {
            return ['blocked' => false, 'blocked_until' => null, 'fail_count' => 0];
        }

        $blockedUntil = $row['blocked_until'] ?? null;
        $failCount = (int)($row['fail_count'] ?? 0);

        if ($blockedUntil !== null && $blockedUntil !== '' && strtotime((string)$blockedUntil) > time()) {
            return ['blocked' => true, 'blocked_until' => (string)$blockedUntil, 'fail_count' => $failCount];
        }

        return ['blocked' => false, 'blocked_until' => null, 'fail_count' => $failCount];
    }

    /**
     * 失敗を加算（ウィンドウ制御 + ブロック判定）
     * @return array [now_blocked(bool), blocked_until(?string), fail_count(int)]
     */
    private static function rateLimitOnFail(PDO $pdo, string $ip, string $email, int $maxAttempts, int $windowSeconds, int $lockSeconds): array
    {
        // 既存行を取得（FOR UPDATE で競合を安全にする）
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("
                SELECT id, fail_count, first_failed_at, blocked_until
                FROM auth_login_throttles
                WHERE ip = :ip AND email = :email
                LIMIT 1
                FOR UPDATE
            ");
            $st->execute([':ip' => $ip, ':email' => $email]);
            $row = $st->fetch();

            $now = (new DateTime('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');
            $nowTs = time();

            if (!$row) {
                // 初回失敗：新規作成
                $failCount = 1;
                $firstFailedAt = $now;
                $blockedUntil = null;

                if ($failCount >= $maxAttempts) {
                    $blockedUntil = date('Y-m-d H:i:s', $nowTs + $lockSeconds);
                }

                $ins = $pdo->prepare("
                    INSERT INTO auth_login_throttles
                      (ip, email, fail_count, first_failed_at, last_failed_at, blocked_until)
                    VALUES
                      (:ip, :email, :c, :ffa, :lfa, :bu)
                ");
                $ins->execute([
                    ':ip'    => $ip,
                    ':email' => $email,
                    ':c'     => $failCount,
                    ':ffa'   => $firstFailedAt,
                    ':lfa'   => $now,
                    ':bu'    => $blockedUntil,
                ]);

                $pdo->commit();
                return [
                    'now_blocked'   => ($blockedUntil !== null),
                    'blocked_until' => $blockedUntil,
                    'fail_count'    => $failCount,
                ];
            }

            // 既存あり
            $failCount = (int)($row['fail_count'] ?? 0);
            $firstFailedAt = (string)($row['first_failed_at'] ?? '');
            $blockedUntilOld = $row['blocked_until'] ?? null;

            // まだブロック中ならカウントは増やさず last_failed_at だけ更新（運用上の追跡は監査が担う）
            if ($blockedUntilOld !== null && $blockedUntilOld !== '' && strtotime((string)$blockedUntilOld) > $nowTs) {
                $upd = $pdo->prepare("
                    UPDATE auth_login_throttles
                    SET last_failed_at = :lfa
                    WHERE ip = :ip AND email = :email
                ");
                $upd->execute([':lfa' => $now, ':ip' => $ip, ':email' => $email]);

                $pdo->commit();
                return [
                    'now_blocked'   => true,
                    'blocked_until' => (string)$blockedUntilOld,
                    'fail_count'    => $failCount,
                ];
            }

            // ウィンドウ判定：first_failed_at から windowSeconds を超えていたらリセット
            $firstTs = $firstFailedAt !== '' ? strtotime($firstFailedAt) : 0;
            if ($firstTs <= 0 || ($nowTs - $firstTs) > $windowSeconds) {
                $failCount = 1;
                $firstFailedAt = $now;
            } else {
                $failCount++;
            }

            $blockedUntil = null;
            if ($failCount >= $maxAttempts) {
                $blockedUntil = date('Y-m-d H:i:s', $nowTs + $lockSeconds);
            }

            $upd = $pdo->prepare("
                UPDATE auth_login_throttles
                SET fail_count = :c,
                    first_failed_at = :ffa,
                    last_failed_at = :lfa,
                    blocked_until = :bu
                WHERE ip = :ip AND email = :email
            ");
            $upd->execute([
                ':c'   => $failCount,
                ':ffa' => $firstFailedAt,
                ':lfa' => $now,
                ':bu'  => $blockedUntil,
                ':ip'  => $ip,
                ':email' => $email,
            ]);

            $pdo->commit();
            return [
                'now_blocked'   => ($blockedUntil !== null),
                'blocked_until' => $blockedUntil,
                'fail_count'    => $failCount,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            // レート制限失敗でログイン自体を止めない（ただし監査で追える）
            return ['now_blocked' => false, 'blocked_until' => null, 'fail_count' => 0];
        }
    }

    /**
     * 成功時：該当キーをリセット（または削除）
     */
    private static function rateLimitOnSuccess(PDO $pdo, string $ip, string $email): void
    {
        try {
            $st = $pdo->prepare("DELETE FROM auth_login_throttles WHERE ip = :ip AND email = :email");
            $st->execute([':ip' => $ip, ':email' => $email]);
        } catch (Throwable $e) {
            // noop
        }
    }

    public static function attempt(string $email, string $password): bool
    {
        $email = trim($email);
        $email = mb_strtolower($email, 'UTF-8');

        $pdo = Db::pdo();
        $ip = self::clientIp();

        // レート制限チェック（ブロック中なら即拒否）
        $rl = self::rateLimitConfig();
        if ($rl['enabled'] && $email !== '') {
            $chk = self::rateLimitCheck($pdo, $ip, $email);
            if ($chk['blocked']) {
                self::auditLoginBlocked($email, (string)$chk['blocked_until'], (int)$chk['fail_count'], 0);
                return false;
            }
        }

        // email でユーザーを取得（退職者判定用に resigned_on も見る）
        $st = $pdo->prepare("SELECT * FROM users WHERE email = :e LIMIT 1");
        $st->execute([':e' => $email]);
        $u = $st->fetch();

        // timing差を減らす：ユーザー不在でも verify は実行
        $hash = $u['password_hash'] ?? null;
        $dummyHash = '$2y$10$5wJ6iL0u3GxXxw0lq2mF0eXQyV4gWkP6mP3pQHk0bK7yqfE9WvZ1e';
        $verifyHash = (!empty($hash) && is_string($hash)) ? $hash : $dummyHash;
        $okPassword = password_verify($password, (string)$verifyHash);

        // ユーザーがいない
        if (!$u) {
            if ($rl['enabled'] && $email !== '') {
                $r = self::rateLimitOnFail($pdo, $ip, $email, $rl['max_attempts'], $rl['window_seconds'], $rl['lock_seconds']);
                if ($r['now_blocked']) {
                    self::auditLoginBlocked($email, (string)$r['blocked_until'], (int)$r['fail_count'], 0);
                    return false;
                }
            }
            self::auditLoginFailed($email, 'no_user', 0);
            return false;
        }

        $uid = (int)($u['id'] ?? 0);

        // password_hash無い
        if (empty($u['password_hash'])) {
            if ($rl['enabled'] && $email !== '') {
                $r = self::rateLimitOnFail($pdo, $ip, $email, $rl['max_attempts'], $rl['window_seconds'], $rl['lock_seconds']);
                if ($r['now_blocked']) {
                    self::auditLoginBlocked($email, (string)$r['blocked_until'], (int)$r['fail_count'], $uid);
                    return false;
                }
            }
            self::auditLoginFailed($email, 'no_password_hash', $uid);
            return false;
        }

        // 退職者は不可
        if (!empty($u['resigned_on'])) {
            if ($rl['enabled'] && $email !== '') {
                $r = self::rateLimitOnFail($pdo, $ip, $email, $rl['max_attempts'], $rl['window_seconds'], $rl['lock_seconds']);
                if ($r['now_blocked']) {
                    self::auditLoginBlocked($email, (string)$r['blocked_until'], (int)$r['fail_count'], $uid);
                    return false;
                }
            }
            self::auditLoginFailed($email, 'resigned', $uid);
            return false;
        }

        // パスワード不一致
        if (!$okPassword) {
            if ($rl['enabled'] && $email !== '') {
                $r = self::rateLimitOnFail($pdo, $ip, $email, $rl['max_attempts'], $rl['window_seconds'], $rl['lock_seconds']);
                if ($r['now_blocked']) {
                    self::auditLoginBlocked($email, (string)$r['blocked_until'], (int)$r['fail_count'], $uid);
                    return false;
                }
            }
            self::auditLoginFailed($email, 'bad_password', $uid);
            return false;
        }

        // 成功（レート制限リセット）
        if ($rl['enabled'] && $email !== '') {
            self::rateLimitOnSuccess($pdo, $ip, $email);
        }

        session_regenerate_id(true);

        $_SESSION[self::SESSION_KEY] = [
            'user_id'   => $uid,
            'cache'     => $u,
            'cached_at' => time(),
        ];

        // Csrf::rotate(); // 任意

        Audit::log('users', 'User', $uid, 'login', $uid);
        return true;
    }

    public static function logout(): void
    {
        $uid = self::id();

        try {
            Audit::log('users', 'User', $uid ?? 0, 'logout', $uid ?? 0);
        } catch (Throwable $e) {
            // noop
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"] ?? '/',
                $params["domain"] ?? '',
                (bool)($params["secure"] ?? false),
                (bool)($params["httponly"] ?? true)
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
