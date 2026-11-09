<?php

namespace App\Modules\OperationalKpis\Support;

/**
 * Per-KPI result state (S09-D06 / D12). Response-level `no_domain` is meta,
 * never a per-KPI state. Omitted (unauthorized) KPIs have no state at all.
 */
enum KpiState: string
{
    case Ok = 'ok';
    case Undefined = 'undefined';
    case Error = 'error';
}
