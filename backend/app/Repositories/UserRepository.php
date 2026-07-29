<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserRepository
{
    /**
     * Tìm user theo email (kể cả đã soft-delete)
     */
    public function findByEmail(string $email)
    {
        return DB::selectOne('SELECT * FROM users WHERE email = ?', [$email]);
    }

    /**
     * Tìm user theo Google ID
     */
    public function findByGoogleId(string $googleId)
    {
        return DB::selectOne('SELECT * FROM users WHERE google_id = ?', [$googleId]);
    }

    /**
     * Tìm user theo Facebook ID
     */
    public function findByFacebookId(string $facebookId)
    {
        return DB::selectOne('SELECT * FROM users WHERE facebook_id = ?', [$facebookId]);
    }

    /**
     * Kiểm tra email đã tồn tại chưa
     */
    public function emailExists(string $email): bool
    {
        return count(DB::select('SELECT * FROM users WHERE email = ?', [$email])) > 0;
    }

    /**
     * Tạo user mới
     */
    public function createUser(array $data): void
    {
        $now = now()->toDateTimeString();
        DB::insert(
            'INSERT INTO users (full_name, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$data['full_name'], $data['email'], $data['password'], $data['role'] ?? 'customer', $now, $now]
        );
    }

    /**
     * Tạo user từ OAuth (Google)
     */
    public function createGoogleUser(string $name, string $email, string $googleId, ?string $avatar): void
    {
        $now = now()->toDateTimeString();
        DB::insert(
            'INSERT INTO users (full_name, email, google_id, password, avatar_url, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$name, $email, $googleId, null, $avatar, 'customer', $now, $now]
        );
    }

    /**
     * Tạo user từ OAuth (Facebook)
     */
    public function createFacebookUser(string $name, string $email, string $facebookId, ?string $avatar): void
    {
        $now = now()->toDateTimeString();
        DB::insert(
            'INSERT INTO users (full_name, email, facebook_id, password, avatar_url, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$name, $email, $facebookId, null, $avatar, 'customer', $now, $now]
        );
    }

    /**
     * Liên kết Google ID vào user hiện tại
     */
    public function linkGoogleId(int $userId, string $googleId, ?string $avatar): void
    {
        $now = now()->toDateTimeString();
        DB::update(
            'UPDATE users SET google_id = ?, avatar_url = COALESCE(avatar_url, ?), updated_at = ? WHERE user_id = ?',
            [$googleId, $avatar, $now, $userId]
        );
    }

    /**
     * Liên kết Facebook ID vào user hiện tại
     */
    public function linkFacebookId(int $userId, string $facebookId, ?string $avatar): void
    {
        $now = now()->toDateTimeString();
        DB::update(
            'UPDATE users SET facebook_id = ?, avatar_url = COALESCE(avatar_url, ?), updated_at = ? WHERE user_id = ?',
            [$facebookId, $avatar, $now, $userId]
        );
    }

    /**
     * Tìm User model theo ID (cho auth login)
     */
    public function findModel(int $userId): ?User
    {
        return User::find($userId);
    }
}
