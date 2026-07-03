<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Services\FormOrderOperationalStatusService::class);

$stats = [
    'total' => 0,
    'by_status' => [],
    'archival' => ['count' => 0, 'by_status' => []],
    'active_or_unknown_course' => ['count' => 0, 'by_status' => []],
    'has_invoice' => 0,
    'no_invoice' => 0,
    'status_completed' => 0,
    'pnedu_provisioned_at' => 0,
    'publigo_course' => 0,
    'order_year' => [],
    'archival_order_year' => [],
];

App\Models\FormOrder::query()
    ->needsHandling()
    ->with(['participants'])
    ->orderBy('id')
    ->chunk(300, function ($orders) use ($svc, &$stats) {
        $courseIds = [];
        foreach ($orders as $order) {
            $cid = $svc->resolveCourseId($order);
            if ($cid) {
                $courseIds[$cid] = true;
            }
        }
        $courseMeta = [];
        if ($courseIds !== []) {
            $courseMeta = App\Models\Course::query()
                ->whereIn('id', array_keys($courseIds))
                ->get(['id', 'end_date', 'source_id_old'])
                ->keyBy('id');
        }

        foreach ($orders as $order) {
            $stats['total']++;
            $ev = $svc->evaluate($order);
            $st = $ev['status'];
            $stats['by_status'][$st] = ($stats['by_status'][$st] ?? 0) + 1;

            if ($order->has_invoice) {
                $stats['has_invoice']++;
            } else {
                $stats['no_invoice']++;
            }
            if ((int) $order->status_completed === 1) {
                $stats['status_completed']++;
            }
            if ($order->pnedu_provisioned_at !== null) {
                $stats['pnedu_provisioned_at']++;
            }

            $year = $order->order_date ? $order->order_date->format('Y') : 'null';
            $stats['order_year'][$year] = ($stats['order_year'][$year] ?? 0) + 1;

            $course = $ev['course_id'] ? ($courseMeta[$ev['course_id']] ?? null) : null;
            if ($course && $course->source_id_old === 'certgen_Publigo') {
                $stats['publigo_course']++;
            }

            $isArchival = $course && $course->end_date && $course->end_date->isPast();
            $bucket = $isArchival ? 'archival' : 'active_or_unknown_course';
            $stats[$bucket]['count']++;
            $stats[$bucket]['by_status'][$st] = ($stats[$bucket]['by_status'][$st] ?? 0) + 1;
            if ($isArchival) {
                $stats['archival_order_year'][$year] = ($stats['archival_order_year'][$year] ?? 0) + 1;
            }
        }
    });

ksort($stats['order_year']);
ksort($stats['archival_order_year']);

$patterns = [
    'invoice_no_fop_email' => 0,
    'invoice_fop_unprovisioned' => 0,
    'invoice_all_provisioned_still_handling' => 0,
    'no_invoice' => 0,
    'no_course_id' => 0,
];

App\Models\FormOrder::query()
    ->needsHandling()
    ->with(['participants'])
    ->chunk(300, function ($orders) use ($svc, &$patterns) {
        foreach ($orders as $order) {
            $courseId = $svc->resolveCourseId($order);
            if (! $courseId) {
                $patterns['no_course_id']++;

                continue;
            }
            $parts = $svc->activeOrderParticipants($order);
            $expected = $parts->count();
            $provisioned = 0;
            foreach ($parts as $fop) {
                if ($svc->isParticipantProvisioned($fop, $courseId)) {
                    $provisioned++;
                }
            }
            if (! $order->has_invoice) {
                $patterns['no_invoice']++;
            } elseif ($expected === 0) {
                $patterns['invoice_no_fop_email']++;
            } elseif ($provisioned < $expected) {
                $patterns['invoice_fop_unprovisioned']++;
            } else {
                $patterns['invoice_all_provisioned_still_handling']++;
            }
        }
    });

$stats['patterns'] = $patterns;

$noCourse = [
    'total' => 0,
    'has_product_id' => 0,
    'has_publigo_only' => 0,
    'has_neither' => 0,
    'has_invoice' => 0,
    'product_id_course_missing' => 0,
    'product_id_course_exists' => 0,
    'legacy_id_old_no_match' => 0,
];

App\Models\FormOrder::query()
    ->needsHandling()
    ->select(['id', 'product_id', 'publigo_product_id', 'invoice_number', 'order_date'])
    ->chunk(500, function ($orders) use (&$noCourse) {
        foreach ($orders as $order) {
            $svc = app(App\Services\FormOrderOperationalStatusService::class);
            if ($svc->resolveCourseId($order)) {
                continue;
            }
            $noCourse['total']++;
            if ($order->has_invoice) {
                $noCourse['has_invoice']++;
            }
            if ($order->product_id) {
                $noCourse['has_product_id']++;
                $exists = App\Models\Course::withTrashed()->whereKey($order->product_id)->exists();
                if ($exists) {
                    $noCourse['product_id_course_exists']++;
                } else {
                    $noCourse['product_id_course_missing']++;
                }
            } elseif ($order->publigo_product_id) {
                $noCourse['has_publigo_only']++;
                $match = App\Models\Course::query()
                    ->where('id_old', (string) $order->publigo_product_id)
                    ->whereNotNull('id_old')->where('id_old', '!=', '')
                    ->exists();
                if (! $match) {
                    $noCourse['legacy_id_old_no_match']++;
                }
            } else {
                $noCourse['has_neither']++;
            }
        }
    });

$stats['no_course_breakdown'] = $noCourse;

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
