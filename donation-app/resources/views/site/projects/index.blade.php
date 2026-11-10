@extends('layouts.site')

@section('title', __('Our projects'))
@section('description', __('See what your donations are building — progress, milestones and news from every project.'))

@section('content')
    <section class="bg-brand-50/60">
        <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
            <h1 class="font-display text-4xl font-bold">{{ __('Our projects') }}</h1>
            <p class="mt-3 max-w-2xl text-ink-700">{{ __('What your donations are building, with progress and news as the work happens.') }}</p>
        </div>
    </section>

    <section class="mx-auto mt-10 max-w-7xl px-4 sm:px-6 lg:px-8">
        @if ($projects->isEmpty())
            <div class="rounded-3xl bg-white p-10 text-center ring-1 ring-ink-900/5">
                <p class="text-ink-700">{{ __('Project pages are coming soon.') }}</p>
                <a href="{{ route('campaigns.index') }}" class="btn-primary mt-6">{{ __('See campaigns') }}</a>
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($projects as $project)
                    <a href="{{ route('projects.show', $project->slug) }}" class="group flex flex-col rounded-3xl bg-white p-6 shadow-sm ring-1 ring-ink-900/5 transition hover:shadow-lg">
                        <span class="text-xs font-semibold uppercase tracking-wider text-brand-600">{{ __($project->status->label()) }}@if ($project->location) · {{ $project->location }}@endif</span>
                        <h2 class="mt-2 font-display text-xl font-semibold group-hover:text-brand-700">{{ $project->name }}</h2>
                        @if ($project->summary)
                            <p class="mt-2 flex-1 text-sm text-ink-700">{{ $project->summary }}</p>
                        @endif
                        <div class="mt-5">
                            <div class="h-2 overflow-hidden rounded-full bg-brand-100" role="progressbar" aria-valuenow="{{ $project->spentPercent() }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Budget used') }}">
                                <div class="h-full rounded-full bg-brand-600" style="width: {{ $project->spentPercent() }}%"></div>
                            </div>
                            <p class="mt-2 text-xs text-ink-500">{{ __(':percent% of the budget used · milestones :done', ['percent' => $project->spentPercent(), 'done' => $project->milestoneProgress()]) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">{{ $projects->links() }}</div>
        @endif
    </section>
@endsection
