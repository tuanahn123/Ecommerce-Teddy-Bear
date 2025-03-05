<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class VariationImage
 *
 * @package App\Models
 *
 * @property int $id
 * @property int $product_variation_id
 * @property string $image_path
 * @property bool $is_primary
 * @property String$created_at
 * @property String$updated_at
 *
 * @property ProductVariation $productVariation
 */
class VariationImage extends Model
{
    use HasFactory;

    /**
     *
     * @var string
     */
    protected $table = 'variation_images';

    /**
     *
     * @var array<int, string>
     */
    protected $fillable = ['product_variation_id', 'image_path', 'is_primary'];

    /**
     *
     * @return BelongsTo
     */
    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }
}
