<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Teaser extends Model
{   
    use HasFactory;

    protected $table = 'teasers';

    protected $fillable = [
        'image',
        'title',
        'lang',
        'landing_id',
    ];

    public function landing()
    {
        return $this->belongsTo(Landing::class);
    }

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'teaser_country')->select('country_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'teaser_category');
    }

    public function advertiser()
    {
        return $this->hasOneThrough(User::class, Landing::class, 'id', 'id', 'landing_id', 'adversiter_id');
    }

    public function clicks()
    {
        return $this->hasMany(StatTeaserClick::class)
            ->join('stat_sessions', 'stat_sessions.hash', '=', 'stat_teaser_clicks.session_hash')
            ->select('stat_teaser_clicks.teaser_id', 'stat_sessions.country_id AS country_id');
    }

    public function views()
    {
        return $this->hasMany(StatTeaserView::class)
            ->join('stat_sessions', 'stat_sessions.hash', '=', 'stat_teaser_views.session_hash')
            ->select('stat_teaser_views.teaser_id', 'stat_sessions.country_id AS country_id');
    }

    /**
     * Make data for select options (Nova stuff).
     *
     * @return array
     */
    public static function variants(): array
    {
        $data = [];
        $rows = self::get();

        foreach ($rows as $row) {
            $data[$row->title] = $row->id;
        }

        return $data;
    }
}
