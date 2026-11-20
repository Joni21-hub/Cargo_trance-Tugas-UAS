<?php

return [
    'group_id' => 'Group A - Maritime Logistics',
    'route_focus' => 'Southeast Asia (Tanjung Priok / Singapore) -> Europe (Rotterdam / Hamburg)',
    'delay_threshold_hours' => env('CARGOTRACE_DELAY_THRESHOLD', 12),
    'primary_exception_types' => ['CUSTOMS_HOLD', 'WEATHER_DELAY', 'PORT_CONGESTION', 'VESSEL_BREAKDOWN'],
];
