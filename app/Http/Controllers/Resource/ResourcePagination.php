<?php

namespace App\Http\Controllers\Resource;

use App\Models\Resource\Category;
use App\Models\Resource\Resource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResourcePagination
{

    /**
     * Retourne la liste des resources les plus stylés
     *
     * @return mixed
     */
    public static function mostResourcesPagination(): mixed
    {
        return Cache::remember('resources:mostResources', 86400, function () {
            $mostResourcesUsers = ResourcePagination::mostResources();
            $mostResources = [];
            foreach ($mostResourcesUsers as $user) {
                $count = Resource::where('user_id', $user->id)->count();
                $mostResources[] = ['name' => $user->displayNameAndLink(), 'url' => $user->authorPage(), 'count' => $count, 'image' => $user->getProfilePhotoUrlAttribute(),];
            }
            return $mostResources;
        });
    }

    public static function mostResources()
    {
        return User::select('users.name', 'users.id', 'users.profile_photo_path', 'users.user_role_id')
            ->addSelect(DB::raw("COUNT(`resource_resources`.`id`) AS `resource`"))
            ->join('resource_resources', 'resource_resources.user_id', '=', 'users.id')
            ->where('resource_resources.is_display', true)
            ->where('resource_resources.is_pending', false)
            ->groupBy('resource_resources.user_id')
            ->groupBy('users.id')
            ->groupBy('users.name')
            ->groupBy('users.profile_photo_path')
            ->groupBy('users.user_role_id')
            ->orderBy('resource', 'DESC')
            ->limit(5)->get();
    }

    public static function paginateAuthor(User $user): LengthAwarePaginator
    {
        return Resource::select("resource_resources.*")
            ->with('version')
            ->with('category')
            ->with('icon')
            ->leftJoin('resource_versions', 'resource_resources.version_id', '=', 'resource_versions.id')
            ->when(!Auth::guest(), function ($query) {
                $currentUser = Auth::user();
                $query->where('resource_resources.is_pending', true)->where('resource_resources.user_id', $currentUser->id)
                    ->orWhere('resource_resources.is_pending', false);
            }, function ($query) {
                $query->where('resource_resources.is_pending', false);
            })
            ->where('resource_resources.is_display', true)
            ->where('resource_resources.user_id', $user->id)
            ->orderBy('resource_versions.created_at', 'DESC')
            ->paginate();
    }

    /**
     * Paginate resources
     *
     * @param Category|null $category
     * @return mixed
     */
    public static function paginate(Category $category = null): mixed
    {
        $search = request()->input('search');

        return Resource::select("resource_resources.*")
            ->with('version')
            ->with('category')
            ->with('icon')
            ->leftJoin('resource_versions', 'resource_resources.version_id', '=', 'resource_versions.id')
            ->leftJoin('users', 'users.id', '=', 'resource_resources.user_id')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('resource_resources.name', 'like', '%' . $search . '%');
                    $query->orWhere('resource_resources.tag', 'like', '%' . $search . '%');
                    $query->orWhere('users.name', 'like', '%' . $search . '%');
                });
            })
            ->when($category, function ($query) use ($category) {
                $query->where('resource_resources.category_id', $category->id);
            })
            ->when(!Auth::guest(), function ($query) {
                $user = Auth::user();
                $query->where('resource_resources.is_pending', true)->where('resource_resources.user_id', $user->id)
                    ->orWhere('resource_resources.is_pending', false);
            }, function ($query) {
                $query->where('resource_resources.is_pending', false);
            })
            ->where('resource_resources.is_display', true)
            ->orderBy('resource_versions.created_at', 'DESC')
            ->paginate();
    }

    /**
     * Renvoie la liste des ressources auxquelles l'utilisateur actuel a accès.
     *
     * @return LengthAwarePaginator
     */
    public static function paginateUserAccessibleResources(): LengthAwarePaginator
    {
        return Resource::select("resource_resources.*")
            ->with(['version', 'category', 'icon', 'buyers'])
            ->leftJoin('resource_versions', 'resource_resources.version_id', '=', 'resource_versions.id')
            ->leftJoin('resource_accesses', 'resource_resources.id', '=', 'resource_accesses.resource_id')
            ->when(Auth::check(), function ($query) {
                $user = user();
                // Filtrer les ressources selon les accès définis dans le modèle Access
                $query->where('resource_accesses.user_id', $user->id);
            })
            ->where('resource_resources.is_display', true)
            ->orderBy('resource_versions.created_at', 'DESC')
            ->paginate();
    }


}
