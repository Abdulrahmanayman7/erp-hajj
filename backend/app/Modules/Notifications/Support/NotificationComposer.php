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
            NotificationType::StockBelowMinimum => [
                'title' => 'مخزون دون الحد الأدنى',
                'body' => $this->clip($this->stockLine($item, $warehouse)),
            ],
        };
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
