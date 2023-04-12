<?php

namespace App\Http\Controllers\Resource;

use App\Http\Controllers\Controller;
use App\Models\Resource\Download;
use App\Models\Resource\Resource;
use App\Models\Resource\Review;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;

class ResourceReviewController extends Controller
{

    /**
     * Show a resource
     *
     * @param string $slug
     * @param Resource $resource
     * @return \Illuminate\Contracts\Foundation\Application|Factory|View|Application|RedirectResponse
     */
    public function index(string $slug, Resource $resource): \Illuminate\Contracts\Foundation\Application|Factory|View|Application|RedirectResponse
    {
        if ($slug != $resource->slug()) return Redirect::route('resources.reviews', ['resource' => $resource->id, 'slug' => $resource->slug()]);
        $reviews = $resource->reviews()->with('version')->with('user')->orderBy('created_at', 'desc')->paginate();
        return view('resources.pages.reviews', ['resource' => $resource, 'reviews' => $reviews]);
    }

    /**
     * @param Resource $resource
     * @return RedirectResponse
     */
    public function indexById(Resource $resource): RedirectResponse
    {
        return Redirect::route('resources.reviews', ['resource' => $resource->id, 'slug' => $resource->slug()]);
    }

    /**
     * Rate a resource
     *
     * @param Request $request
     * @param Resource $resource
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function store(Request $request, Resource $resource): RedirectResponse
    {

        $this->validate($request, ['rate' => 'required', 'message' => 'required|max:5000|min:30',]);

        $user = user();
        if (empty(Download::hasAlreadyDownload($resource->version, $user))) {
            return Redirect::back()->with('toast', createToast('error', __('resources.reviews.errors.download.title'), __('resources.reviews.errors.download.content'), 5000));
        }

        if ($resource->user_id === $user->id) {
            return Redirect::back()->with('toast', createToast('error', __('resources.reviews.errors.self.title'), __('resources.reviews.errors.self.content'), 5000));
        }

        $review = Review::where('user_id', $user->id)->where('version_id', $resource->version_id)->count();
        if ($review > 0) {
            return Redirect::back()->with('toast', createToast('error', __('resources.reviews.errors.already.title'), __('resources.reviews.errors.already.content'), 5000));
        }

        $rate = $request['rate'];
        if (!in_array($rate, [1, 2, 3, 4, 5])) {
            return Redirect::back()->with('toast', createToast('error', __('resources.reviews.errors.rate.title'), __('resources.reviews.errors.rate.content'), 5000));
        }

        Review::create(['user_id' => $user->id, 'resource_id' => $resource->id, 'version_id' => $resource->version_id, 'score' => $rate, 'review' => $request['message']]);

        $resource->clear('count.review');
        $resource->clear('count.score');
        $resource->clear('count.score.version');
        $resource->clear('count.review.version');

        return Redirect::back()->with('toast', createToast('success', __('resources.reviews.success.title'), __('resources.reviews.success.content'), 5000));
    }

    /**
     * Delete a review
     *
     * @param Review $review
     * @return RedirectResponse
     */
    public function deleteReview(Review $review): RedirectResponse
    {

        $user = user();
        if (!$user->role->isModerator()) {
            return Redirect::back()->with('toast', createToast('success', __('resources.reviews.success.title'), __('resources.reviews.success.content'), 5000));
        }

        $resource = $review->resource;
        $review->delete();

        $resource->clear('count.review');
        $resource->clear('count.score');
        $resource->clear('count.score.version');
        $resource->clear('count.review.version');

        return Redirect::back()->with('toast', createToast('success', 'Action effectuée', 'Vous venez de supprimer cette review.', 5000));
    }
}
