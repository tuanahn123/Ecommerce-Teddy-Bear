<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class ProductVariation
 *
 * @package App\Models
 *
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property float|null $price
 * @property float|null $discount_price
 * @property int $stock_quantity
 * @property bool $is_default
 * @property bool $status
 * @property String $created_at
 * @property String $updated_at
 *
 * @property-read Product $product
 * @property VariationAttribute[] $attributes
 * @property VariationImage[] $images
 */
class ProductVariation extends Model
{
    use HasFactory;
    
    /**
     * Tên bảng trong cơ sở dữ liệu
     *
     * @var string
     */
    protected $table = 'product_variations';

    /**
     * Các cột có thể gán giá trị hàng loạt
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id', 
        'sku', 
        'price', 
        'discount_price', 
        'stock_quantity', 
        'is_default', 
        'status'
    ];

    /**
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     *
     * @return HasMany
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(VariationAttribute::class);
    }

    /**
     *
     * @return HasMany
     */
    public function images(): HasMany
    {
        return $this->hasMany(VariationImage::class);
    }
}
