<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\PostCategory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Public Updates pages (PDF section 10). Only posts whose publish time has
 * passed exist here: drafts and scheduled posts 404, whatever their slug.
 */
class UpdatesController extends Controller
{
    /**
     * All published posts, newest first.
     *
     * @return View
     */
    public function index(): View
    {
        return $this->listing(null);
    }

    /**
     * Published posts in one category.
     *
     * @param  PostCategory  $post_category
     * @return View
     */
    public function category(PostCategory $post_category): View
    {
        return $this->listing($post_category);
    }

    /**
     * One published post.
     *
     * @param  string  $slug
     * @return View
     */
    public function show(string $slug): View
    {
        $post = Post::query()->published()->with('category')->where('slug', $slug)->firstOrFail();

        return view('updates.show', [
            'post' => $post,
            'is_preview' => false,
            'related_posts' => Post::query()
                ->published()
                ->with('category')
                ->whereBelongsTo($post->category, 'category')
                ->whereKeyNot($post->id)
                ->newestFirst()
                ->limit(3)
                ->get(),
        ]);
    }

    /**
     * Staff/admin preview of a draft or scheduled post, from a signed link
     * (route middleware "signed" + "auth"). The page is marked noindex. A
     * post that is already live redirects to its public address.
     *
     * @param  Post  $post
     * @return View|RedirectResponse
     *
     * @throws AuthorizationException If the signed-in user is not staff/admin.
     */
    public function preview(Post $post): View|RedirectResponse
    {
        Gate::authorize('view', $post);

        if ($post->isPublished()) {
            return redirect()->to($post->publicUrl());
        }

        $post->load('category');

        return view('updates.show', [
            'post' => $post,
            'related_posts' => collect(),
            'is_preview' => true,
        ]);
    }

    /**
     * @param  PostCategory|null  $current_category
     * @return View
     */
    private function listing(?PostCategory $current_category): View
    {
        /** @var LengthAwarePaginator<int, Post> $posts */
        $posts = Post::query()
            ->published()
            ->with('category')
            ->when($current_category, fn ($query) => $query->whereBelongsTo($current_category, 'category'))
            ->newestFirst()
            ->paginate((int) config('madeeasy.blog.per_page'));

        return view('updates.index', [
            'posts' => $posts,
            'categories' => PostCategory::query()->orderBy('name')->get(),
            'current_category' => $current_category,
        ]);
    }
}
