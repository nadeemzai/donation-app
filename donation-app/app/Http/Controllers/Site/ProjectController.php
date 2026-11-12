<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ImpactUpdate;
use App\Models\Project;
use Illuminate\View\View;

/**
 * Public project pages (FR-PRJ-02). Only projects with "Show on the website" on. Shows name,
 * summary, budget progress, milestones and published updates — never payees, amounts paid to
 * people or anything about beneficiaries.
 */
class ProjectController extends Controller
{
    public function index(): View
    {
        return view('site.projects.index', [
            'projects' => Project::query()->public()->with('fund')->latest('starts_on')->latest('id')->paginate(12),
        ]);
    }

    public function show(string $slug): View
    {
        $project = Project::query()->public()->where('slug', $slug)->with(['fund', 'milestones'])->firstOrFail();

        return view('site.projects.show', [
            'project' => $project,
            'updates' => ImpactUpdate::query()->public()->where('project_id', $project->id)->with('media')->latest('published_at')->limit(20)->get(),
        ]);
    }
}
