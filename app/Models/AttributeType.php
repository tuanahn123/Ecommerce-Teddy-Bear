<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * Class AttributeType
 *
 * @property integer $id
 * @property string $name
 * @property string $display_name
 * @property string $created_at
 * @property string $updated_at
 *
 * @property AttributeValue[] $attributeValues
 */
class AttributeType extends Model
{
    use HasFactory;
    protected $table = 'attribute_types';

    protected $fillable = ['id','name','display_name'];

    public function attributeValues()
    {
        return $this->hasMany(AttributeValue::class);
    }
}
