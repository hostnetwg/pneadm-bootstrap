<?php

namespace App\Enums\Analytics;

enum AnalyticsCategory: string
{
    case Campaign = 'campaign';
    case Landing = 'landing';
    case OrderForm = 'order_form';
    case Validation = 'validation';
    case Conversion = 'conversion';
    case Payment = 'payment';
    case Invoice = 'invoice';
    case AbTest = 'ab_test';
    case System = 'system';
}
