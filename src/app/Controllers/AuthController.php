<?php

/**
 * AuthController.php
 * ============================================================
 * 役割:
 * - ログイン画面表示（GET /）
 * - ログイン処理（POST /login）
 * - ログアウト処理（POST /logout）
 *
 * 設計意図:
 * - 認証状態は Auth に委譲（セッション + TTL再取得）
 * - POST は CSRF を必須（Csrf::check）
 * - 失敗時のメッセージは固定（ユーザー存在有無を漏らさない）
 *
 * 追加（安全側）:
 * - Auth::check() は「セッションにuser_idがあるだけ」の場合があるため、
 *   画面側のログイン判定には Auth::user() で実在/有効（退職者除外）を確定させる
 * - Response::redirect が exit しない実装でも事故らないよう return を入れる
 * - login の入力不足（空）を先に弾き、常に同一文言で返す（タイミング差/漏えい抑制）
 */

class AuthController
{
    /**
     * ログイン画面
     * - すでにログインしている場合は users 一覧へ
     */
    public function loginForm(): void
    {
        // 安全側: check() ではなく user() で「有効ユーザー」を確定
        if (Auth::user()) {
            Response::redirect('/users');
            return;
        }

        Response::view('login', ['title' => 'ログイン']);
    }

    /**
     * ログイン処理
     * - CSRF検証 → 入力整形 → Auth::attempt
     */
    public function login(): void
    {
        // POSTはCSRF必須（hidden _token or X-CSRF-Token header）
        Csrf::check($_POST['_token'] ?? null);

        // 入力整形
        $email = trim((string)($_POST['email'] ?? ''));
        $email = mb_strtolower($email, 'UTF-8'); // メールは小文字運用が無難（必要なければ削除OK）
        $password = (string)($_POST['password'] ?? '');

        // 入力不足でも文言は固定（存在有無・仕様漏えい抑制）
        if ($email === '' || $password === '') {
            $error = 'ユーザーIDまたはパスワードが不正です。';
            Response::view('login', [
                'title' => 'ログイン',
                'error' => $error,
                'old'   => ['email' => $email],
            ]);
            return;
        }

        // 認証成功 → 遷移
        if (Auth::attempt($email, $password)) {
            Response::redirect('/cars');
            return;
        }

        // 認証失敗（情報漏えい防止のため文言は固定）
        $error = 'ユーザーIDまたはパスワードが不正です。';
        Response::view('login', [
            'title' => 'ログイン',
            'error' => $error,
            'old'   => ['email' => $email], // 再入力を楽にする
        ]);
    }

    /**
     * ログアウト
     * - CSRF検証 → Auth::logout → ログイン画面へ
     */
    public function logout(): void
    {
        Csrf::check($_POST['_token'] ?? null);

        Auth::logout();

        Response::redirect('/');
    }
}
