<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class VariationAttribute
 *
 * @package App\Models
 *
 * @property int $id
 * @property int $product_variation_id
 * @property int $attribute_value_id
 * @property String $created_at
 * @property String $updated_at
 *
 * @property ProductVariation $variation
 * @property AttributeValue $attributeValue
 */
class VariationAttribute extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong cơ sở dữ liệu
     *
     * @var string
     */
    protected $table = 'variation_attributes';

    /**
     *
     * @var array<int, string>
     */
    protected $fillable = ['product_variation_id', 'attribute_value_id'];

    /**
     *
     * @return BelongsTo
     */
    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    /**
     *
     * @return BelongsTo
     */
    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }
}
