<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ProductImage
 *
 * @package App\Models
 *
 * @property int $id
 * @property int $product_id
 * @property string $image_path
 * @property bool $is_primary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read Product $product
 */
class ProductImage extends Model
{
    use HasFactory;

    /**
     *
     * @var string
     */
    protected $table = 'product_images';

    /**
     *
     * @var array<int, string>
     */
    protected $fillable = ['product_id', 'image_path', 'is_primary'];

    /**
     *
     * @return BelongsTo
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
