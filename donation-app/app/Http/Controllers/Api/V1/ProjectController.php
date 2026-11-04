<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ImpactUpdateResource;
use App\Http\Resources\V1\ProjectResource;
use App\Models\ImpactUpdate;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Projects
 *
 * Public projects and their impact updates (FR-PRJ-02). Only projects shown on the website.
 */
class ProjectController extends Controller
{
    /**
     * Projects
     *
     * Public projects, newest first, with budget used and milestone progress. Paged.
     *
     * @unauthenticated
     */
    public function index(Request $request): JsonResponse
    {
        $page = Project::query()->public()->with('fund')->latest('starts_on')->latest('id')
            ->paginate(ApiResponse::perPage($request, 12));

        return ApiResponse::paginated($page, ProjectResource::class);
    }

    /**
     * One project
     *
     * With its description and milestones.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Example: water-pump-thar
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $project = Project::query()->public()->where('slug', $slug)->with(['fund', 'milestones'])->firstOrFail();

        return ApiResponse::success(ProjectResource::make($project)->resolve($request));
    }

    /**
     * Project updates
     *
     * Published impact updates on one project, newest first. Paged.
     *
     * @unauthenticated
     *
     * @urlParam slug string required Example: water-pump-thar
     */
    public function updates(Request $request, string $slug): JsonResponse
    {
        $project = Project::query()->public()->where('slug', $slug)->firstOrFail();

        $page = ImpactUpdate::query()->public()->where('project_id', $project->id)
            ->with(['media', 'project'])
            ->latest('published_at')->latest('id')
            ->paginate(ApiResponse::perPage($request, 10));

        return ApiResponse::paginated($page, ImpactUpdateResource::class);
    }

    /**
     * Project updates feed
     *
     * The latest published impact updates from every public project. Each item says which
     * project it belongs to. Paged. (Campaign updates stay in `impact-updates`.)
     *
     * @unauthenticated
     */
    public function feed(Request $request): JsonResponse
    {
        $page = ImpactUpdate::query()->public()
            ->with(['media', 'project'])
            ->latest('published_at')->latest('id')
            ->paginate(ApiResponse::perPage($request, 10));

        return ApiResponse::paginated($page, ImpactUpdateResource::class);
    }
}
