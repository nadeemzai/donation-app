<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;

// Code rules from CLAUDE.md, enforced on every `composer check`.

arch('every app file declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('no debugging helpers are left in app code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('env() is only read in config files')
    ->expect('env')
    ->not->toBeUsedIn('App');

arch('app/Enums contains only enums')
    ->expect('App\Enums')
    ->toBeEnums();

arch('API controllers never query the database directly')
    ->expect('App\Http\Controllers\Api')
    ->not->toUse('Illuminate\Support\Facades\DB');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);
