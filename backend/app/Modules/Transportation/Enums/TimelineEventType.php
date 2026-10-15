<?php

namespace App\Modules\Transportation\Enums;

enum TimelineEventType: string
{
    case MovementCreated = 'movement_created';
    case MovementPlanned = 'movement_planned';
    case MovementStarted = 'movement_started';
    case MovementCompleted = 'movement_completed';
    case MovementCancelled = 'movement_cancelled';
    case RunCreated = 'run_created';
    case RunBoardingStarted = 'run_boarding_started';
    case RunDeparted = 'run_departed';
    case RunArrived = 'run_arrived';
    case RunCompleted = 'run_completed';
    case RunCancelled = 'run_cancelled';
    case VehicleAssigned = 'vehicle_assigned';
    case DriverAssigned = 'driver_assigned';
    case SupervisorAssigned = 'supervisor_assigned';
    case PassengerAssigned = 'passenger_assigned';
    case PassengerBoarded = 'passenger_boarded';
    case PassengerArrived = 'passenger_arrived';
    case PassengerNoShow = 'passenger_no_show';
    case PassengerCancelled = 'passenger_cancelled';
    case PassengerEntitlementOverridden = 'passenger_entitlement_overridden';
    case ReviewRaised = 'review_raised';
    case ReviewResolved = 'review_resolved';
    case EventCorrected = 'event_corrected';

    /**
     * Entity timestamp updated when an event of this type is corrected (I8).
     * Null → not correctable.
     *
     * @return array{0: 'movement'|'run'|'passenger', 1: string}|null
     */
    public function correctableTarget(): ?array
    {
        return match ($this) {
            self::MovementStarted => ['movement', 'started_at'],
            self::MovementCompleted => ['movement', 'completed_at'],
            self::RunBoardingStarted => ['run', 'boarding_started_at'],
            self::RunDeparted => ['run', 'departed_at'],
            self::RunArrived => ['run', 'arrived_at'],
            self::RunCompleted => ['run', 'completed_at'],
            self::PassengerBoarded => ['passenger', 'boarded_at'],
            self::PassengerArrived => ['passenger', 'arrived_at'],
            self::PassengerNoShow => ['passenger', 'no_show_at'],
            default => null,
        };
    }
}
