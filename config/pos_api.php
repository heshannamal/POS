<?php

return [
    // Explicit application clients are required to use Bearer tokens (not web cookies).
    'token_expiry_days' => (int) env('POS_API_TOKEN_EXPIRY_DAYS', 14),
    'max_devices_per_user_tenant' => (int) env('POS_API_MAX_DEVICES', 10),
    'product_page_size' => 50,
];
