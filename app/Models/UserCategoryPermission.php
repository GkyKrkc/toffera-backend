<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir kullanıcının, tek bir kategoride sahip olduğu yetkilerin tek gerçek
 * kaynağı. AccountTypeGroup <-> Category pivot'u değiştiğinde ya da
 * kullanıcının grubu değiştiğinde CategoryAccessService::syncFromGroup()
 * ile buraya senkronize edilir (source='group' satırlar). Admin bir
 * kullanıcı üzerinde elle değişiklik yaptığında source='manual' olur ve
 * bir sonraki grup senkronu o satıra dokunmaz.
 */
class UserCategoryPermission extends Model
{
    protected $fillable = [
        'user_id', 'category_id',
        'can_add_portfolio', 'portfolio_limit',
        'can_offer',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'can_add_portfolio' => 'boolean',
            'can_offer'         => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
