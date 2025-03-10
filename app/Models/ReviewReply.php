<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'admin_id',
        'content'
    ];

    /**
     * Get the review that owns the reply.
     */
    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * Get the admin who wrote the reply.
     */
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
