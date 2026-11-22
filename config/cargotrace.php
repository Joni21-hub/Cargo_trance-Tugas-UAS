<?php

return [
    'group_id' => 'Group A - Maritime Logistics',
    'route_focus' => 'Southeast Asia (Tanjung Priok / Singapore) -> Europe (Rotterdam / Hamburg)',
    'delay_threshold_hours' => env('CARGOTRACE_DELAY_THRESHOLD', 12),
    'enable_demo_tools' => env('CARGOTRACE_ENABLE_DEMO_TOOLS', true),
    'primary_exception_types' => [
        'CUSTOMS_HOLD',
        'WEATHER_DELAY',
        'PORT_CONGESTION',
        'DAMAGED',
        'MISROUTED',
        'DOCUMENTATION_ISSUE',
    ],
];
