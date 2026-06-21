<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    protected $primaryKey = 'user_id';

    /**
     * The attributes that are mass assignable.
     * FIX C2: Loại bỏ role, status, reward_points khỏi fillable
     * để tránh mass-assignment attack. Dùng forceFill() khi admin cần thay đổi.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'date_of_birth',
        'google_id',
        'facebook_id',
        'referral_code',
        'referred_by',
        'affiliate_registered_at',
        'is_affiliate',
    ];

    /**
     * FIX C2: Các field nhạy cảm không cho phép mass-assignment.
     * Phải dùng forceFill() hoặc gán trực tiếp khi admin thay đổi.
     */
    protected $guarded_attributes = ['role', 'status', 'reward_points'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'date_of_birth'            => 'date',
            'password'                 => 'hashed',
            'is_affiliate'             => 'boolean',
            'affiliate_registered_at'  => 'datetime',
        ];
    }

    /**
     * Quan hệ: User có nhiều Address
     */
    public function addresses()
    {
        return $this->hasMany(\App\Models\Address::class, 'user_id', 'user_id');
    }

    /**
     * Quan hệ: User có 1 Cart (giỏ hàng)
     */
    public function cart()
    {
        return $this->hasOne(Cart::class, 'user_id', 'user_id');
    }

    /**
     * Quan hệ: User có nhiều UserCoupon (mã giảm giá đã lưu)
     */
    public function userCoupons()
    {
        return $this->hasMany(UserCoupon::class, 'user_id', 'user_id');
    }

    public function returnRequests()
    {
        return $this->hasMany(ReturnRequest::class, 'user_id', 'user_id');
    }

    /**
     * Quan hệ: User có 1 Wallet (ví cá nhân)
     */
    public function wallet()
    {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id');
    }

    // ==================== Affiliate Relationships ====================

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by', 'user_id');
    }

    public function referredUsers()
    {
        return $this->hasMany(User::class, 'referred_by', 'user_id');
    }

    public function affiliateClicks()
    {
        return $this->hasMany(AffiliateClick::class, 'referrer_id', 'user_id');
    }

    public function affiliateConversions()
    {
        return $this->hasMany(AffiliateConversion::class, 'referrer_id', 'user_id');
    }

    public function affiliateWithdrawals()
    {
        return $this->hasMany(AffiliateWithdrawal::class, 'user_id', 'user_id');
    }

    // ==================== JWT Methods ====================

    /**
     * Lấy identifier (khóa chính) để mã hóa vào JWT payload (claim "sub").
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Thêm các custom claims vào JWT payload (ví dụ: role).
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role ?? 'customer',
        ];
    }
}
