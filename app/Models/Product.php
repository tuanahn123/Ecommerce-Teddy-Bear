<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Product
 *
 * @property integer $id
 * @property string $name
 * @property string $slug
 * @property integer $category_id
 * @property float $base_price
 * @property string|null $description
 * @property boolean $featured
 * @property boolean $status
 * @property string $created_at
 * @property string $updated_at
 *
 * @property ProductImage[] $images
 * @property ProductVariation[] $variations
 */
class Product extends Model
{
    use HasFactory;
    protected $table = 'products';

    protected $fillable = ['name', 'slug', 'category_id', 'base_price', 'description', 'featured', 'status'];

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }
    public function categories()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
