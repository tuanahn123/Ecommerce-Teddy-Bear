<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class AttributeValue
 *
 * @package App\Models
 * 
 * @property int $id
 * @property int $attribute_type_id
 * @property string $value
 * @property string $display_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read AttributeType $attributeType
 * @property-read \Illuminate\Database\Eloquent\Collection|VariationAttribute[] $variationAttributes
 */
class AttributeValue extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong cơ sở dữ liệu
     *
     * @var string
     */
    protected $table = 'attribute_values';

    /**
     * Các cột có thể gán giá trị hàng loạt
     *
     * @var array<int, string>
     */
    protected $fillable = ['attribute_type_id', 'value', 'display_value'];

    /**
     * Quan hệ với bảng `attribute_types`
     * 
     * @return BelongsTo
     */
    public function attributeType(): BelongsTo
    {
        return $this->belongsTo(AttributeType::class);
    }

    /**
     * Quan hệ với bảng `variation_attributes`
     * 
     * @return HasMany
     */
    public function variationAttributes(): HasMany
    {
        return $this->hasMany(VariationAttribute::class);
    }
}
