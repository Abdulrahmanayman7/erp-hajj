<?php

namespace App\Modules\SeasonPilgrims\Activation;

use App\Modules\SeasonPilgrims\Contracts\SeasonPilgrimActivationRequirement;
use App\Modules\SeasonPilgrims\Exceptions\SeasonPilgrimDomainException;
use App\Modules\SeasonPilgrims\Models\SeasonPilgrim;
use Illuminate\Contracts\Container\Container;

final class SeasonPilgrimActivationValidator
{
    public const REQUIREMENT_TAG = 'season_pilgrims.activation_requirements';

    public function __construct(private readonly Container $container) {}

    /**
     * @return list<string> violation codes
     */
    public function violations(SeasonPilgrim $seasonPilgrim): array
    {
        $violations = [];

        /** @var SeasonPilgrimActivationRequirement $requirement */
        foreach ($this->container->tagged(self::REQUIREMENT_TAG) as $requirement) {
            $code = $requirement->check($seasonPilgrim);
            if ($code !== null) {
                $violations[] = $code;
            }
        }

        return $violations;
    }

    public function assertSatisfied(SeasonPilgrim $seasonPilgrim): void
    {
        $violations = $this->violations($seasonPilgrim);

        if ($violations !== []) {
            throw SeasonPilgrimDomainException::activationRequirementsNotMet($violations);
        }
    }
}
