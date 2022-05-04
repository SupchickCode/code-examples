<?php

namespace App\Models;

use App\Enums\ConversionStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Conversion extends Model
{
    protected $table = 'conversions';

    protected $fillable = [
        'user_id',
        'manager_id',
        'stream_id',
        'advertiser_id',
        'click_uuid',
        'adv_internal_id',
        'status',
        'payout',
        'user_payout',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function advertiser()
    {
        return $this->belongsTo(User::class, 'advertiser_id');
    }

    public function stream()
    {
        return $this->belongsTo(Stream::class);
    }

    public function session()
    {
        return $this->hasOneThrough(StatSession::class, StatTeaserClick::class, 'uuid', 'hash', 'click_uuid', 'session_hash');
    }

    public function teaser()
    {
        return $this->hasOneThrough(Teaser::class, StatTeaserClick::class, 'uuid', 'id', 'click_uuid', 'teaser_id');
    }

    public function teaser_click()
    {
        return $this->belongsTo(StatTeaserClick::class, 'click_uuid', 'uuid');
    }

    public function page_view()
    {
        return $this->hasOneThrough(StatPageView::class, StatTeaserClick::class, 'uuid', 'uuid', 'click_uuid', 'page_view_uuid');
    }

    public static function getTypes()
    {
        return [
            ConversionStatusEnum::PENDING => __('text.pending'),
            ConversionStatusEnum::APPROVED => __('text.approved'),
            ConversionStatusEnum::REJECTED => __('text.rejected'),
            ConversionStatusEnum::PAID => __('text.paid')
        ];
    }

    /**
     * Применить область запроса к переданному построителю запросов.
     *
     * @param  Builder $builder
     * @param  User $user
     * @return Builder
     */
    public function scopeAvailableForUser(Builder $builder, User $user): Builder
    {
        if (!$user->hasPermissionTo('view_conversions')) {
            if ($user->hasPermissionTo('view_own_conversions')) {
                switch (true) {
                    case $user->isWebmaster():
                        $builder->orWhere('user_id', $user->id);
                        break;
                    case $user->isAdvertiser():
                        $builder->orWhere('advertiser_id', $user->id);
                        break;
                    case $user->isWebmasterManager():
                        $userIds = $user->users->pluck('id');
                        $builder->orWhereIn('user_id', is_array($userIds) ? $userIds : [$userIds]);
                        break;
                    case  $user->isAdvertiserManager():
                        $builder->orWhereHas('advertiser', function ($query) use ($user) {
                            $query->where('manager_id', $user->id);
                        });
                }
            }
        }

        return $builder;
    }
}
