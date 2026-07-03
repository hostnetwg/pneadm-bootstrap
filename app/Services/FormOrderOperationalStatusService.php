<?php

namespace App\Services;

use App\Models\FormOrder;
use App\Models\FormOrderParticipant;
use App\Models\Participant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wyliczany status operacyjny zamówienia — pełne zamknięcie: uczestnicy na szkoleniu + faktura.
 */
class FormOrderOperationalStatusService
{
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_NEEDS_INVOICE = 'needs_invoice';

    public const STATUS_NEEDS_PROVISIONING = 'needs_provisioning';

    public const STATUS_PARTIALLY_PROCESSED = 'partially_processed';

    public const STATUS_INCONSISTENT = 'inconsistent';

    public const STATUS_LEGACY_HANDLED = 'legacy_handled';

    /**
     * @return array{
     *   status: string,
     *   label: string,
     *   badge_class: string,
     *   expected_count: int,
     *   provisioned_count: int,
     *   warnings: array<int, string>,
     *   course_id: int|null
     * }
     */
    public function evaluate(FormOrder $order): array
    {
        $warnings = [];
        $courseId = $this->resolveCourseId($order);

        if ($order->cancelled_at !== null) {
            $participants = $this->activeOrderParticipants($order);
            $expected = $participants->count();
            $provisioned = $expected > 0 && $courseId
                ? $participants->filter(fn (FormOrderParticipant $fop) => $this->isParticipantProvisioned($fop, $courseId))->count()
                : 0;

            if ($provisioned > 0) {
                $warnings[] = 'Zamówienie anulowane, ale co najmniej jeden uczestnik nadal ma aktywny dostęp do szkolenia.';
            }

            foreach ($participants as $fop) {
                if ($fop->participant_id === null && $provisioned > 0) {
                    $warnings[] = 'Brak powiązania participant_id — dostęp wykryty tylko po e-mailu (legacy). Przy anulowaniu nie usuwano automatycznie.';
                    break;
                }
            }

            return $this->buildResult(
                self::STATUS_CANCELLED,
                $expected,
                $provisioned,
                $warnings,
                $courseId
            );
        }

        if ($order->legacy_handled_at !== null) {
            $participants = $this->activeOrderParticipants($order);
            $expected = $participants->count();
            $provisioned = $expected > 0 && $courseId
                ? $participants->filter(fn (FormOrderParticipant $fop) => $this->isParticipantProvisioned($fop, $courseId))->count()
                : 0;

            return $this->buildResult(
                self::STATUS_LEGACY_HANDLED,
                $expected,
                $provisioned,
                [],
                $courseId
            );
        }

        $participants = $this->activeOrderParticipants($order);
        $expected = $participants->count();

        if ($expected === 0) {
            $warnings[] = 'Brak aktywnych uczestników zamówienia z adresem e-mail.';

            return $this->buildResult(self::STATUS_INCONSISTENT, 0, 0, $warnings, $courseId);
        }

        if ($courseId === null) {
            $warnings[] = 'Nie można ustalić szkolenia (product_id / legacy Publigo).';

            return $this->buildResult(self::STATUS_INCONSISTENT, $expected, 0, $warnings, null);
        }

        $provisioned = 0;
        foreach ($participants as $fop) {
            if ($this->isParticipantProvisioned($fop, $courseId)) {
                $provisioned++;
            }
        }

        if ($order->pnedu_provisioned_at !== null && $provisioned === 0) {
            $warnings[] = 'Oznaczono provision PNEDU, ale brak aktywnego uczestnika na szkoleniu.';
        }

        if ($order->has_invoice && $provisioned === 0) {
            $warnings[] = 'Wystawiono fakturę, ale uczestnik nie został dodany do szkolenia.';
        }

        if ($order->status_completed == 1 && $provisioned === 0) {
            $warnings[] = 'Legacy: status „Zakończone” bez uczestnika na szkoleniu.';
        }

        foreach ($participants as $fop) {
            if ($fop->participant_id !== null) {
                $linked = Participant::query()->find($fop->participant_id);
                if ($linked && ! $linked->trashed() && (int) $linked->course_id !== $courseId) {
                    $warnings[] = 'Powiązany uczestnik (#'.$fop->participant_id.') należy do innego szkolenia.';
                }
            }
        }

        $hasDuplicateEnrollments = $this->hasDuplicateActiveEnrollments($participants, $courseId);
        if ($hasDuplicateEnrollments) {
            $warnings[] = 'Wykryto duplikaty aktywnych uczestników (ten sam e-mail na szkoleniu).';
        }

        if ($provisioned === $expected) {
            if ($hasDuplicateEnrollments) {
                return $this->buildResult(self::STATUS_INCONSISTENT, $expected, $provisioned, $warnings, $courseId);
            }

            if (! $order->isBillingComplete()) {
                $warnings[] = 'Uczestnik dodany do szkolenia, ale faktura nie została wystawiona.';

                return $this->buildResult(self::STATUS_NEEDS_INVOICE, $expected, $provisioned, $warnings, $courseId);
            }

            return $this->buildResult(self::STATUS_PROCESSED, $expected, $provisioned, $warnings, $courseId);
        }

        if ($provisioned > 0) {
            return $this->buildResult(self::STATUS_PARTIALLY_PROCESSED, $expected, $provisioned, $warnings, $courseId);
        }

        if (! empty($warnings)) {
            return $this->buildResult(self::STATUS_INCONSISTENT, $expected, $provisioned, $warnings, $courseId);
        }

        return $this->buildResult(self::STATUS_NEEDS_PROVISIONING, $expected, $provisioned, $warnings, $courseId);
    }

    public function needsAttention(FormOrder $order): bool
    {
        $status = $this->evaluate($order)['status'];

        return in_array($status, [
            self::STATUS_NEEDS_PROVISIONING,
            self::STATUS_PARTIALLY_PROCESSED,
            self::STATUS_INCONSISTENT,
        ], true);
    }

    /**
     * Czy zamówienie wymaga obsługi przez personel (nieanulowane i bez pełnego zamknięcia: FV + uczestnicy).
     */
    public function needsOperationalHandling(FormOrder $order): bool
    {
        if ($order->cancelled_at !== null || $order->legacy_handled_at !== null) {
            return false;
        }

        return ! ($order->isBillingComplete() && $this->isProcessed($order));
    }

    public function isProcessed(FormOrder $order): bool
    {
        return $this->evaluate($order)['status'] === self::STATUS_PROCESSED;
    }

    /**
     * Filtr „Do obsługi” — nieanulowane zamówienia bez faktury i/lub bez pełnego dostępu uczestników.
     * Dotyczy też opłaconych online (PayU, PayNow) bez numeru FV — wymagają ręcznego zamknięcia operacyjnego.
     */
    public function scopeNeedsOperationalHandling(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $courseSql = $this->resolveCourseIdSql($table);
        $provisioned = $this->participantProvisionedExistsSql('fop_unprov', $courseSql);

        return $query
            ->whereNull("{$table}.cancelled_at")
            ->whereNull("{$table}.legacy_handled_at")
            ->where(function ($outer) use ($table, $provisioned) {
                $outer->where(function ($noBilling) use ($table) {
                    $noBilling->whereNull("{$table}.invoice_exempt_at")
                        ->where(function ($noInv) use ($table) {
                            $noInv->whereNull("{$table}.invoice_number")
                                ->orWhere("{$table}.invoice_number", '')
                                ->orWhere("{$table}.invoice_number", '0');
                        });
                })->orWhereNotExists(function ($sub) use ($table) {
                    $sub->selectRaw('1')
                        ->from('form_order_participants as fop_has')
                        ->whereColumn('fop_has.form_order_id', "{$table}.id")
                        ->whereNull('fop_has.deleted_at')
                        ->whereRaw("TRIM(fop_has.participant_email) != ''");
                })->orWhereExists(function ($sub) use ($table, $provisioned) {
                    $sub->selectRaw('1')
                        ->from('form_order_participants as fop_unprov')
                        ->whereColumn('fop_unprov.form_order_id', "{$table}.id")
                        ->whereNull('fop_unprov.deleted_at')
                        ->whereRaw("TRIM(fop_unprov.participant_email) != ''")
                        ->whereRaw("NOT ({$provisioned})");
                });
            });
    }

    /**
     * Filtr „Do obsługi (aktywne)” — otwarta kolejka tylko dla trwających szkoleń z rozpoznanym kursem.
     */
    public function scopeNeedsActiveOperationalHandling(Builder $query): Builder
    {
        $query = $this->scopeNeedsOperationalHandling($query);
        $query->where($this->linkedToActiveCourseConstraint());

        return $query;
    }

    /**
     * @return \Closure(\Illuminate\Database\Eloquent\Builder): void
     */
    public function linkedToActiveCourseConstraint(): \Closure
    {
        return function ($query) {
            $query->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('courses')
                    ->where('courses.end_date', '>=', now())
                    ->where(function ($match) {
                        $match->whereColumn('courses.id', 'form_orders.product_id')
                            ->orWhere(function ($legacy) {
                                $legacy->whereNotNull('courses.id_old')
                                    ->where('courses.id_old', '!=', '')
                                    ->whereColumn('courses.id_old', 'form_orders.publigo_product_id');
                            });
                    });
            });
        };
    }

    /**
     * Liczba zamówień „do obsługi” per kurs (courses.id) — zapytanie przez JOIN, zgodne z ONLY_FULL_GROUP_BY.
     *
     * @param  array<int, int>  $courseIds
     * @return array<int, int>
     */
    public function countNeedsHandlingByCourseIds(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        $provisionedSql = $this->participantProvisionedExistsSql('fop_cnt', 'c.id');

        return DB::connection('mysql')
            ->table('courses as c')
            ->join('form_orders as fo', function ($join) {
                $join->whereNull('fo.deleted_at')
                    ->where(function ($q) {
                        $q->whereColumn('fo.product_id', 'c.id')
                            ->orWhere(function ($q2) {
                                $q2->whereNotNull('c.id_old')
                                    ->where('c.id_old', '!=', '')
                                    ->whereColumn('fo.publigo_product_id', 'c.id_old');
                            });
                    });
            })
            ->whereIn('c.id', $courseIds)
            ->where('c.end_date', '>=', now())
            ->whereNull('fo.cancelled_at')
            ->whereNull('fo.legacy_handled_at')
            ->where(function ($outer) use ($provisionedSql) {
                $outer->where(function ($noBilling) {
                    $noBilling->whereNull('fo.invoice_exempt_at')
                        ->where(function ($noInv) {
                            $noInv->whereNull('fo.invoice_number')
                                ->orWhere('fo.invoice_number', '')
                                ->orWhere('fo.invoice_number', '0');
                        });
                })->orWhereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('form_order_participants as fop_has')
                        ->whereColumn('fop_has.form_order_id', 'fo.id')
                        ->whereNull('fop_has.deleted_at')
                        ->whereRaw("TRIM(fop_has.participant_email) != ''");
                })->orWhereExists(function ($sub) use ($provisionedSql) {
                    $sub->selectRaw('1')
                        ->from('form_order_participants as fop_cnt')
                        ->whereColumn('fop_cnt.form_order_id', 'fo.id')
                        ->whereNull('fop_cnt.deleted_at')
                        ->whereRaw("TRIM(fop_cnt.participant_email) != ''")
                        ->whereRaw("NOT ({$provisionedSql})");
                });
            })
            ->groupBy('c.id')
            ->select('c.id as course_id', DB::raw('COUNT(DISTINCT fo.id) as cnt'))
            ->pluck('cnt', 'course_id')
            ->map(fn ($cnt) => (int) $cnt)
            ->all();
    }

    /**
     * Filtr „Nieprzetworzone” — wymaga dodania uczestnika(ów) do szkolenia.
     */
    public function scopeNeedsAttention(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->whereNull("{$table}.cancelled_at")
            ->whereExists(function ($sub) use ($table) {
                $sub->selectRaw('1')
                    ->from('form_order_participants as fop_need')
                    ->whereColumn('fop_need.form_order_id', "{$table}.id")
                    ->whereNull('fop_need.deleted_at')
                    ->whereRaw("TRIM(fop_need.participant_email) != ''");
            })
            ->whereExists(function ($sub) use ($table) {
                $courseSql = $this->resolveCourseIdSql($table);
                $provisioned = $this->participantProvisionedExistsSql('fop_unprov', $courseSql);

                $sub->selectRaw('1')
                    ->from('form_order_participants as fop_unprov')
                    ->whereColumn('fop_unprov.form_order_id', "{$table}.id")
                    ->whereNull('fop_unprov.deleted_at')
                    ->whereRaw("TRIM(fop_unprov.participant_email) != ''")
                    ->whereRaw("NOT ({$provisioned})");
            });
    }

    /**
     * Filtr „Przetworzone” — wszyscy uczestnicy na szkoleniu, faktura wystawiona, nieanulowane.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->whereNull("{$table}.cancelled_at")
            ->where(function ($billing) use ($table) {
                $billing->where(function ($inv) use ($table) {
                    $inv->whereNotNull("{$table}.invoice_number")
                        ->where("{$table}.invoice_number", '!=', '')
                        ->where("{$table}.invoice_number", '!=', '0');
                })->orWhereNotNull("{$table}.invoice_exempt_at");
            })
            ->whereExists(function ($sub) use ($table) {
                $sub->selectRaw('1')
                    ->from('form_order_participants as fop_exp')
                    ->whereColumn('fop_exp.form_order_id', "{$table}.id")
                    ->whereNull('fop_exp.deleted_at')
                    ->whereRaw("TRIM(fop_exp.participant_email) != ''");
            })
            ->whereNotExists(function ($sub) use ($table) {
                $courseSql = $this->resolveCourseIdSql($table);
                $provisioned = $this->participantProvisionedExistsSql('fop_miss', $courseSql);

                $sub->selectRaw('1')
                    ->from('form_order_participants as fop_miss')
                    ->whereColumn('fop_miss.form_order_id', "{$table}.id")
                    ->whereNull('fop_miss.deleted_at')
                    ->whereRaw("TRIM(fop_miss.participant_email) != ''")
                    ->whereRaw("NOT ({$provisioned})");
            });
    }

    public function resolveCourseId(FormOrder $order): ?int
    {
        $productId = (int) ($order->product_id ?? 0);
        if ($productId > 0) {
            return $productId;
        }

        $publigoId = trim((string) ($order->publigo_product_id ?? ''));
        if ($publigoId !== '' && $publigoId !== '0') {
            $legacy = \App\Models\Course::query()
                ->where('id_old', $publigoId)
                ->whereNotNull('id_old')
                ->where('id_old', '!=', '')
                ->value('id');

            return $legacy ? (int) $legacy : null;
        }

        return null;
    }

    /**
     * SQL: ID kursu dla wiersza form_orders (alias $foAlias).
     * product_id = 0 traktujemy jak brak powiązania (legacy import).
     */
    public function resolveCourseIdSql(string $foAlias = 'form_orders'): string
    {
        return "COALESCE(
            NULLIF({$foAlias}.product_id, 0),
            (
                SELECT c_res.id FROM courses c_res
                WHERE c_res.deleted_at IS NULL
                  AND c_res.id_old IS NOT NULL
                  AND c_res.id_old != ''
                  AND c_res.id_old = NULLIF(NULLIF({$foAlias}.publigo_product_id, ''), '0')
                LIMIT 1
            )
        )";
    }

    /**
     * SQL EXISTS: czy uczestnik zamówienia (alias fop) ma aktywny dostęp na kursie ($courseIdSql).
     */
    public function participantProvisionedExistsSql(string $fopAlias, string $courseIdSql): string
    {
        return "EXISTS (
            SELECT 1 FROM participants p_enr
            WHERE p_enr.deleted_at IS NULL
              AND p_enr.course_id = ({$courseIdSql})
              AND (
                  ({$fopAlias}.participant_id IS NOT NULL AND p_enr.id = {$fopAlias}.participant_id)
                  OR (
                      {$fopAlias}.participant_id IS NULL
                      AND TRIM({$fopAlias}.participant_email) != ''
                      AND (
                          p_enr.email_normalized = LOWER(TRIM({$fopAlias}.participant_email))
                          OR LOWER(TRIM(p_enr.email)) = LOWER(TRIM({$fopAlias}.participant_email))
                      )
                  )
              )
        )";
    }

    public function isParticipantProvisioned(FormOrderParticipant $fop, int $courseId): bool
    {
        if ($fop->participant_id !== null) {
            $linked = Participant::query()->find($fop->participant_id);
            if ($linked && ! $linked->trashed() && (int) $linked->course_id === $courseId) {
                return true;
            }
        }

        $email = strtolower(trim((string) ($fop->participant_email ?? '')));
        if ($email === '') {
            return false;
        }

        return Participant::query()
            ->where('course_id', $courseId)
            ->where(function ($q) use ($email) {
                $q->where('email_normalized', $email)
                    ->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
            })
            ->exists();
    }

    /**
     * @return Collection<int, FormOrderParticipant>
     */
    public function activeOrderParticipants(FormOrder $order): Collection
    {
        if ($order->relationLoaded('participants')) {
            return $order->participants
                ->filter(fn (FormOrderParticipant $p) => $p->deleted_at === null)
                ->filter(fn (FormOrderParticipant $p) => trim((string) ($p->participant_email ?? '')) !== '')
                ->values();
        }

        return $order->participants()
            ->whereNull('deleted_at')
            ->whereRaw("TRIM(participant_email) != ''")
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, string>  $warnings
     * @return array{status: string, label: string, badge_class: string, expected_count: int, provisioned_count: int, warnings: array<int, string>, course_id: int|null}
     */
    private function buildResult(
        string $status,
        int $expected,
        int $provisioned,
        array $warnings,
        ?int $courseId
    ): array {
        return [
            'status' => $status,
            'label' => $this->labelForStatus($status),
            'badge_class' => $this->badgeClassForStatus($status),
            'expected_count' => $expected,
            'provisioned_count' => $provisioned,
            'warnings' => $warnings,
            'course_id' => $courseId,
        ];
    }

    private function labelForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_CANCELLED => 'Anulowane',
            self::STATUS_PROCESSED => 'Przetworzone',
            self::STATUS_NEEDS_INVOICE => 'Do wystawienia FV',
            self::STATUS_NEEDS_PROVISIONING => 'Do dodania uczestnika',
            self::STATUS_PARTIALLY_PROCESSED => 'Częściowo dodano',
            self::STATUS_INCONSISTENT => 'Wymaga kontroli',
            self::STATUS_LEGACY_HANDLED => 'Legacy — zamknięte',
            default => $status,
        };
    }

    private function badgeClassForStatus(string $status): string
    {
        return match ($status) {
            self::STATUS_CANCELLED => 'bg-secondary',
            self::STATUS_PROCESSED => 'bg-success',
            self::STATUS_NEEDS_INVOICE => 'bg-warning text-dark',
            self::STATUS_NEEDS_PROVISIONING => 'bg-danger',
            self::STATUS_PARTIALLY_PROCESSED => 'bg-warning text-dark',
            self::STATUS_INCONSISTENT => 'bg-dark',
            self::STATUS_LEGACY_HANDLED => 'bg-secondary',
            default => 'bg-light text-dark',
        };
    }

    /**
     * @param  Collection<int, FormOrderParticipant>  $participants
     */
    private function hasDuplicateActiveEnrollments(Collection $participants, int $courseId): bool
    {
        foreach ($participants as $fop) {
            $email = strtolower(trim((string) ($fop->participant_email ?? '')));
            if ($email === '') {
                continue;
            }

            $count = Participant::query()
                ->where('course_id', $courseId)
                ->where(function ($q) use ($email) {
                    $q->where('email_normalized', $email)
                        ->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                })
                ->count();

            if ($count > 1) {
                return true;
            }
        }

        return false;
    }
}
