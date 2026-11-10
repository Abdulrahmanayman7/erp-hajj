<?php

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\NotificationType;

final class NotificationComposer
{
    /**
     * @param  array<string, scalar|null>  $context
     * @return array{title: string, body: string|null}
     */
    public function compose(NotificationType $type, array $context = []): array
    {
        $number = $this->str($context['number'] ?? null);
        $title = $this->str($context['title'] ?? null);
        $name = $this->str($context['name'] ?? null);
        $item = $this->str($context['item'] ?? null);
        $warehouse = $this->str($context['warehouse'] ?? null);

        return match ($type) {
            NotificationType::ContractExpiringSoon => [
                'title' => 'عقد قرب انتهاء صلاحيته',
                'body' => $this->clip($number !== '' ? "العقد {$number} يقترب من تاريخ الانتهاء." : 'أحد العقود يقترب من تاريخ الانتهاء.'),
            ],
            NotificationType::ContractExpired => [
                'title' => 'انتهى عقد',
                'body' => $this->clip($number !== '' ? "انتهت صلاحية العقد {$number}." : 'انتهت صلاحية أحد العقود.'),
            ],
            NotificationType::MeetingScheduled => [
                'title' => 'تم جدولة اجتماع',
                'body' => $this->clip($number !== '' ? "تم جدولة الاجتماع {$number}." : 'تم جدولة اجتماع جديد.'),
            ],
            NotificationType::MeetingRescheduled => [
                'title' => 'تم إعادة جدولة اجتماع',
                'body' => $this->clip($number !== '' ? "تم تغيير موعد الاجتماع {$number}." : 'تم تغيير موعد أحد الاجتماعات.'),
            ],
            NotificationType::MeetingCancelled => [
                'title' => 'تم إلغاء اجتماع',
                'body' => $this->clip($number !== '' ? "تم إلغاء الاجتماع {$number}." : 'تم إلغاء أحد الاجتماعات.'),
            ],
            NotificationType::MeetingStartingSoon => [
                'title' => 'اجتماع يبدأ قريبًا',
                'body' => $this->clip($number !== '' ? "الاجتماع {$number} سيبدأ قريبًا." : 'أحد الاجتماعات سيبدأ قريبًا.'),
            ],
            NotificationType::DecisionSubmitted => [
                'title' => 'قرار بانتظار الاعتماد',
                'body' => $this->clip($number !== '' ? "القرار {$number} بانتظار الاعتماد." : 'هناك قرار بانتظار الاعتماد.'),
            ],
            NotificationType::DecisionApproved => [
                'title' => 'تم اعتماد قرار',
                'body' => $this->clip($number !== '' ? "تم اعتماد القرار {$number}." : 'تم اعتماد أحد القرارات.'),
            ],
            NotificationType::DecisionReturnedToDraft => [
                'title' => 'أُعيد قرار إلى المسودة',
                'body' => $this->clip($number !== '' ? "أُعيد القرار {$number} إلى المسودة." : 'أُعيد أحد القرارات إلى المسودة.'),
            ],
            NotificationType::DecisionClosed => [
                'title' => 'تم إغلاق قرار',
                'body' => $this->clip($number !== '' ? "تم إغلاق القرار {$number}." : 'تم إغلاق أحد القرارات.'),
            ],
            NotificationType::DecisionCancelled => [
                'title' => 'تم إلغاء قرار',
                'body' => $this->clip($number !== '' ? "تم إلغاء القرار {$number}." : 'تم إلغاء أحد القرارات.'),
            ],
            NotificationType::TaskAssigned => [
                'title' => 'تم تعيين مهمة لك',
                'body' => $this->clip($this->taskLine($number, $title)),
            ],
            NotificationType::TaskReassigned => [
                'title' => 'أُعيد تعيين مهمة لك',
                'body' => $this->clip($this->taskLine($number, $title)),
            ],
            NotificationType::TaskCompleted => [
                'title' => 'اكتملت مهمة',
                'body' => $this->clip($number !== '' ? "اكتملت المهمة {$number}." : 'اكتملت إحدى المهام.'),
            ],
            NotificationType::TaskDueSoon => [
                'title' => 'مهمة قرب موعدها',
                'body' => $this->clip($number !== '' ? "المهمة {$number} يقترب موعد استحقاقها." : 'إحدى المهام يقترب موعد استحقاقها.'),
            ],
            NotificationType::TaskOverdue => [
                'title' => 'مهمة متأخرة',
                'body' => $this->clip($number !== '' ? "المهمة {$number} متأخرة عن موعدها." : 'إحدى المهام متأخرة عن موعدها.'),
            ],
            NotificationType::CustodyAssigned => [
                'title' => 'تم إسناد عهدة لك',
                'body' => $this->clip($name !== '' ? "تم إسناد العهدة/الأصل {$name} إليك." : 'تم إسناد عهدة إليك.'),
            ],
            NotificationType::CustodyReturned => [
                'title' => 'تم إرجاع عهدة',
                'body' => $this->clip($number !== '' ? "تم إرجاع العهدة {$number}." : 'تم إرجاع إحدى العهد.'),
            ],
            NotificationType::CustodyExpectedReturnSoon => [
                'title' => 'اقتراب موعد إرجاع عهدة',
                'body' => $this->clip($number !== '' ? "يقترب موعد إرجاع العهدة {$number}." : 'يقترب موعد إرجاع إحدى العهد.'),
            ],
            NotificationType::CustodyOverdue => [
                'title' => 'عهدة متأخرة الإرجاع',
                'body' => $this->clip($number !== '' ? "العهدة {$number} متأخرة عن موعد الإرجاع." : 'إحدى العهد متأخرة عن موعد الإرجاع.'),
            ],
            NotificationType::SupplierEvaluationMonitoringRecommended => [
                'title' => 'توصية بمراقبة مورد',
                'body' => sprintf(
                    'التقييم %s للمورد %s أوصى بالمراقبة (الدرجة: %s).',
                    (string) ($context['evaluation_number'] ?? ''),
                    (string) ($context['supplier_number'] ?? $context['supplier_id'] ?? ''),
                    (string) ($context['total_score'] ?? '—'),
                ),
            ],
            NotificationType::PurchaseApprovalSlaEscalated => [
                'title' => 'تصعيد اعتماد طلب شراء',
                'body' => $this->clip(
                    $number !== ''
                        ? sprintf(
                            'تجاوزت خطوة اعتماد الطلب %s (المستوى %s) مهلة SLA.',
                            $number,
                            (string) ($context['level_order'] ?? '—'),
                        )
                        : 'تجاوزت خطوة اعتماد مهلة SLA.',
                ),
            ],
            NotificationType::PurchaseApprovalPoolReady => [
                'title' => 'طلب شراء بانتظار اعتمادك',
                'body' => $this->clip(
                    $number !== ''
                        ? sprintf('الطلب %s بانتظار إجراء الاعتماد في المستوى %s.', $number, (string) ($context['level_order'] ?? '—'))
                        : 'يوجد طلب شراء بانتظار اعتمادك.',
                ),
            ],
            NotificationType::PurchaseApprovalRoutingBlocked => [
                'title' => 'تعذّر توجيه اعتماد طلب شراء',
                'body' => $this->clip(
                    $number !== ''
                        ? sprintf(
                            'تعذّر توجيه الطلب %s (%s).',
                            $number,
                            (string) ($context['routing_block_code'] ?? 'PR_ROUTING_BLOCKED'),
                        )
                        : 'تعذّر توجيه أحد طلبات الشراء لمسار الاعتماد.',
                ),
            ],
            NotificationType::PurchaseApprovalNoEligibleFinal => [
                'title' => 'لا يوجد معتمد نهائي مؤهل',
                'body' => $this->clip(
                    $number !== ''
                        ? sprintf('الطلب %s بلا معتمد نهائي مؤهل بعد استبعاد مراجع المالية.', $number)
                        : 'لا يوجد معتمد نهائي مؤهل لطلب شراء.',
                ),
            ],
            NotificationType::PurchaseRequestReturned => [
                'title' => 'أُعيد طلب الشراء للتعديل',
                'body' => $this->clip($number !== '' ? "أُعيد الطلب {$number} للتعديل." : 'أُعيد أحد طلبات الشراء للتعديل.'),
            ],
            NotificationType::PurchaseRequestRejected => [
                'title' => 'رُفض طلب الشراء',
                'body' => $this->clip($number !== '' ? "رُفض الطلب {$number}." : 'رُفض أحد طلبات الشراء.'),
            ],
            NotificationType::PurchaseRequestApproved => [
                'title' => 'اعتمد طلب الشراء',
                'body' => $this->clip($number !== '' ? "اعتمد الطلب {$number} نهائياً." : 'اعتمد أحد طلبات الشراء نهائياً.'),
            ],
            NotificationType::PurchaseRequestCancelApproved => [
                'title' => 'أُلغي طلب شراء معتمد',
                'body' => $this->clip($number !== '' ? "أُلغي الطلب المعتمد {$number}." : 'أُلغي أحد طلبات الشراء المعتمدة.'),
            ],
            NotificationType::ProcurementRfqPublished => [
                'title' => 'نُشر طلب عروض أسعار',
                'body' => $this->clip(
                    sprintf(
                        'نُشر طلب عروض الأسعار %s المرتبط بطلب الشراء %s.',
                        (string) ($context['rfq_number'] ?? '—'),
                        $number !== '' ? $number : '—',
                    ),
                ),
            ],
            NotificationType::ProcurementRfqSupplierSelected => [
                'title' => 'تمّت ترسية طلب عروض أسعار',
                'body' => $this->clip(
                    sprintf(
                        'أُرسي طلب عروض الأسعار %s المرتبط بطلب الشراء %s.',
                        (string) ($context['rfq_number'] ?? '—'),
                        $number !== '' ? $number : '—',
                    ),
                ),
            ],
            NotificationType::PurchaseOrderIssued => [
                'title' => 'صدر أمر شراء',
                'body' => $this->clip(
                    sprintf(
                        'صدر أمر الشراء %s لطلب الشراء %s.',
                        (string) ($context['po_number'] ?? '—'),
                        $number !== '' ? $number : '—',
                    ),
                ),
            ],
            NotificationType::GoodsReceiptPosted => [
                'title' => 'رُحّل سند استلام',
                'body' => $this->clip(
                    sprintf(
                        'رُحّل سند الاستلام %s لأمر الشراء %s.',
                        (string) ($context['grn_number'] ?? '—'),
                        (string) ($context['po_number'] ?? '—'),
                    ),
                ),
            ],
            NotificationType::PurchaseOrderClosed => [
                'title' => 'أُقفل أمر شراء',
                'body' => $this->clip(
                    sprintf(
                        'أُقفل أمر الشراء %s المرتبط بطلب الشراء %s.',
                        (string) ($context['po_number'] ?? '—'),
                        $number !== '' ? $number : '—',
                    ),
                ),
            ],
            NotificationType::StockBelowMinimum => [
                'title' => 'مخزون دون الحد الأدنى',
                'body' => $this->clip($this->stockLine($item, $warehouse)),
            ],
            NotificationType::RiskAssigned => [
                'title' => 'تم تعيين خطر لك',
                'body' => $this->clip($number !== '' ? "تم تعيين الخطر {$number} إليك للمتابعة." : 'تم تعيين خطر تشغيلي إليك.'),
            ],
            NotificationType::ComplaintAssigned => [
                'title' => 'تم تعيين شكوى لك',
                'body' => $this->clip($number !== '' ? "تم تعيين الشكوى {$number} إليك للمعالجة." : 'تم تعيين شكوى إليك للمعالجة.'),
            ],
            NotificationType::ComplaintEscalated => [
                'title' => 'تم تصعيد شكوى إليك',
                'body' => $this->clip($number !== '' ? "تم تصعيد الشكوى {$number} إليك." : 'تم تصعيد شكوى إليك.'),
            ],
            NotificationType::RiskSeverityAlert => [
                'title' => 'تنبيه خطورة خطر تشغيلي',
                'body' => $this->clip($this->riskSeverityLine($number, $this->str($context['severity'] ?? null))),
            ],
            NotificationType::ComplaintResolved => [
                'title' => 'تم اقتراح حل لشكوى',
                'body' => $this->clip($number !== '' ? "الشكوى {$number} بانتظار الإغلاق بعد الحل." : 'شكوى بانتظار الإغلاق بعد الحل.'),
            ],
            NotificationType::ComplaintReopened => [
                'title' => 'أُعيد فتح شكوى',
                'body' => $this->clip($number !== '' ? "أُعيد فتح الشكوى {$number}." : 'أُعيد فتح إحدى الشكاوى.'),
            ],
            NotificationType::SeasonReminderDue => [
                'title' => $title !== '' ? mb_substr($title, 0, 255) : 'تذكير موسمي',
                'body' => $this->clip($this->str($context['body'] ?? null) !== '' ? $this->str($context['body'] ?? null) : 'حان موعد تذكير موسمي مُعدّ في المنشأة.'),
            ],
            NotificationType::ExecutiveAlertRiskHigh => [
                'title' => 'تنبيه تشغيلي: خطر مرتفع',
                'body' => $this->clip($number !== '' ? "الخطر {$number} مفتوح بمستوى خطورة مرتفع أو حرج." : 'يوجد خطر مفتوح بمستوى خطورة مرتفع أو حرج.'),
            ],
            NotificationType::ExecutiveAlertProcurementOverdue => [
                'title' => 'تنبيه تشغيلي: اعتماد شراء متأخر',
                'body' => $this->clip($number !== '' ? "خطوة اعتماد الطلب {$number} متأخرة بأكثر من 7 أيام." : 'توجد خطوة اعتماد شراء متأخرة بأكثر من 7 أيام.'),
            ],
        };
    }

    private function riskSeverityLine(string $number, string $severity): string
    {
        $band = $severity !== '' ? $severity : 'مرتفعة';
        if ($number !== '') {
            return "الخطر {$number} بمستوى خطورة {$band}.";
        }

        return "خطر تشغيلي بمستوى خطورة {$band}.";
    }

    private function taskLine(string $number, string $title): string
    {
        if ($number !== '' && $title !== '') {
            return "تم تعيين المهمة {$number}: {$title}";
        }
        if ($number !== '') {
            return "تم تعيين المهمة {$number}.";
        }

        return 'تم تعيين مهمة لك.';
    }

    private function stockLine(string $item, string $warehouse): string
    {
        if ($item !== '' && $warehouse !== '') {
            return "الصنف {$item} في المستودع {$warehouse} دون الحد الأدنى.";
        }
        if ($item !== '') {
            return "الصنف {$item} دون الحد الأدنى للمخزون.";
        }

        return 'يوجد صنف دون الحد الأدنى للمخزون.';
    }

    private function str(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $text = trim((string) $value);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';

        return $text;
    }

    private function clip(?string $body): ?string
    {
        if ($body === null || $body === '') {
            return null;
        }

        return mb_substr($body, 0, 1000);
    }
}
