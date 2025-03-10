<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\ReviewReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // TODO Thêm đánh giá sản phẩm

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $user = Auth::user();
        $review = Review::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Đánh giá đã được thêm',
            'review' => $review
        ], 201);
    }

    // TODO Sửa đánh giá
    public function update(Request $request, $id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Đánh giá không tồn tại'], 404);
        }

        // Kiểm tra quyền sở hữu
        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa đánh giá này'], 403);
        }

        $validated = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'status' => 'sometimes|in:pending,approved,rejected',
        ]);

        $review->update($validated);

        return response()->json(['message' => 'Đánh giá đã được cập nhật', 'review' => $review]);
    }

    // TODO Xóa đánh giá
    public function destroy($id)
    {
        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Đánh giá không tồn tại'], 404);
        }

        // Kiểm tra quyền sở hữu
        if ($review->user_id !== Auth::id()) {
            return response()->json(['message' => 'Bạn không có quyền xóa đánh giá này'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Đánh giá đã được xóa']);
    }

    public function getReviewsByProduct($product_id)
    {
        $reviews = Review::where('product_id', $product_id)
            ->with('user:id,name', 'replies')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['reviews' => $reviews]);
    }
    // TODO Admin - Xem tất cả đánh giá
    public function getAllReviews(Request $request)
    {
        // Kiểm tra quyền admin
        if (!Auth::user()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập'], 403);
        }

        $query = Review::with(['user:id,name', 'product:id,name', 'replies']);

        // Lọc theo trạng thái nếu có
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Lọc theo sản phẩm nếu có
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        // Sắp xếp theo ngày tạo mới nhất
        $reviews = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json(['reviews' => $reviews]);
    }

    // TODO Admin - Phê duyệt/Từ chối đánh giá
    public function updateReviewStatus(Request $request, $id)
    {
        // Kiểm tra quyền admin
        if (!Auth::user()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập'], 403);
        }

        $review = Review::find($id);

        if (!$review) {
            return response()->json(['message' => 'Đánh giá không tồn tại'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => 'Trạng thái đánh giá đã được cập nhật',
            'review' => $review
        ]);
    }

    // TODO Admin - Trả lời đánh giá
    public function replyToReview(Request $request, $review_id)
    {
        // Kiểm tra quyền admin
        if (!Auth::user()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập'], 403);
        }

        $review = Review::find($review_id);

        if (!$review) {
            return response()->json(['message' => 'Đánh giá không tồn tại'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $reply = ReviewReply::create([
            'review_id' => $review->id,
            'admin_id' => Auth::id(),
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Đã trả lời đánh giá thành công',
            'reply' => $reply->load('admin:id,name')
        ], 201);
    }

    // TODO Admin - Cập nhật trả lời
    public function updateReply(Request $request, $reply_id)
    {
        // Kiểm tra quyền admin
        if (!Auth::user()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập'], 403);
        }

        $reply = ReviewReply::find($reply_id);

        if (!$reply) {
            return response()->json(['message' => 'Phản hồi không tồn tại'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $reply->update([
            'content' => $validated['content'],
        ]);

        return response()->json([
            'message' => 'Đã cập nhật phản hồi thành công',
            'reply' => $reply
        ]);
    }

    // TODO Admin - Xóa phản hồi
    public function deleteReply($reply_id)
    {
        // Kiểm tra quyền admin
        if (!Auth::user()) {
            return response()->json(['message' => 'Bạn không có quyền truy cập'], 403);
        }

        $reply = ReviewReply::find($reply_id);

        if (!$reply) {
            return response()->json(['message' => 'Phản hồi không tồn tại'], 404);
        }

        $reply->delete();

        return response()->json([
            'message' => 'Đã xóa phản hồi thành công'
        ]);
    }
}
