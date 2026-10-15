<?php

use App\Modules\SeasonPilgrims\Contracts\SeasonResourceDeletionChecker;
use App\Modules\SeasonPilgrims\Models\Season;
use App\Modules\SeasonPilgrims\Models\SeasonFamilyGroup;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrimGroup;
use App\Modules\SeasonPilgrims\Models\SeasonProgram;
use App\Modules\SeasonPilgrims\Support\SeasonResourceDeletionReferences;

/**
 * Registers a checker that blocks deletion of every resource of $class.
 */
function blockDeletionOf(string $class): void
{
    app()->bind('test.deletion-checker', fn () => new class($class) implements SeasonResourceDeletionChecker
    {
        public function __construct(private readonly string $class) {}

        public function blocksDeletion(Season|SeasonProgram|SeasonPilgrimGroup|SeasonFamilyGroup $resource): bool
        {
            return $resource instanceof $this->class;
        }
    });
    app()->tag(['test.deletion-checker'], SeasonResourceDeletionReferences::CHECKER_TAG);
}

it('blocks deleting a draft season referenced by an external checker', function (): void {
    actingAsTenantOwner();
    $season = spCreateSeason();
    blockDeletionOf(Season::class);

    spaDeleteJson("/api/v1/seasons/{$season['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'SEASON_RESOURCE_REFERENCED');
    expect(Season::query()->withoutGlobalScopes()->whereKey($season['id'])->exists())->toBeTrue();
});

it('blocks deleting programs, pilgrim groups and family groups referenced by an external checker', function (string $class, string $uri): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $h = seasonHeader($season['id']);
    $id = match ($class) {
        SeasonProgram::class => spCreateProgram($season['id'])['id'],
        SeasonPilgrimGroup::class => spCreateGroup($season['id'])['id'],
        SeasonFamilyGroup::class => spCreateFamily($season['id'])['id'],
    };
    blockDeletionOf($class);

    spaDeleteJson("{$uri}/{$id}", $h)->assertStatus(422)->assertJsonPath('code', 'SEASON_RESOURCE_REFERENCED');
})->with([
    'program' => [SeasonProgram::class, '/api/v1/season-programs'],
    'pilgrim group' => [SeasonPilgrimGroup::class, '/api/v1/season-pilgrim-groups'],
    'family group' => [SeasonFamilyGroup::class, '/api/v1/season-family-groups'],
]);

it('allows deletion when no checker reports a reference', function (): void {
    actingAsTenantOwner();
    $season = spActiveSeason();
    $h = seasonHeader($season['id']);
    $program = spCreateProgram($season['id']);
    blockDeletionOf(SeasonPilgrimGroup::class);

    spaDeleteJson("/api/v1/season-programs/{$program['id']}", $h)->assertOk();
});
