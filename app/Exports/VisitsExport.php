<?php

namespace App\Exports;

use App\Models\Visit;
use Illuminate\Support\Collection;

class VisitsExport
{
    protected $visits;

    public function __construct($visits)
    {
        $this->visits = $visits;
    }

    public function export()
    {
        // Create Excel file using PHPExcel (old library)
        $phpExcel = new \PHPExcel();
        $sheet = $phpExcel->getActiveSheet();
        
        // Set headers - Arabic
        $headers = [
            'A1' => 'رقم الزيارة',
            'B1' => 'اسم المتجر',
            'C1' => 'جهة الاتصال',
            'D1' => 'رقم الجوال',
            'E1' => 'نوع النشاط',
            'F1' => 'تاريخ الزيارة',
            'G1' => 'اسم المندوب',
            'H1' => 'الحالة',
            'I1' => 'فئة المنتج',
            'J1' => 'عدد القطع',
            'K1' => 'نطاق الميزانية',
            'L1' => 'أهداف التصوير',
            'M1' => 'نوع الخدمة',
            'N1' => 'المكان المفضل',
            'O1' => 'ملاحظات المندوب',
        ];

        // Set header values
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        }

        // Auto-size columns
        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add data
        $row = 2;
        foreach ($this->visits as $visit) {
            // Status translation
            $statusMap = [
                'draft' => 'مسودة',
                'submitted' => 'مُرسلة',
                'pending_review' => 'قيد المراجعة',
                'action_required' => 'يتطلب إجراء',
                'approved' => 'موافق عليها',
                'quotation_sent' => 'تم إرسال العرض',
                'closed_won' => 'مغلقة - فوز',
                'closed_lost' => 'مغلقة - خسارة',
            ];

            // Location translation
            $locationMap = [
                'client_location' => 'موقع العميل',
                'action_studio' => 'استوديو أكشن جروب',
                'external' => 'موقع خارجي',
            ];

            // Shooting goals
            $shootingGoals = [];
            if ($visit->shooting_goals) {
                $goals = is_array($visit->shooting_goals) ? $visit->shooting_goals : json_decode($visit->shooting_goals, true);
                $goalsMap = [
                    'social_media' => 'تسويق عبر وسائل التواصل',
                    'in_store' => 'عرض داخل المتجر',
                    'content_update' => 'تحديث المحتوى',
                    'other' => 'أخرى',
                ];
                foreach ($goals as $goal) {
                    $shootingGoals[] = $goalsMap[$goal] ?? $goal;
                }
            }
            if ($visit->shooting_goals_other_text) {
                $shootingGoals[] = $visit->shooting_goals_other_text;
            }

            // Service types
            $serviceTypes = [];
            if ($visit->service_types) {
                $types = is_array($visit->service_types) ? $visit->service_types : json_decode($visit->service_types, true);
                $typesMap = [
                    'product_photo' => 'تصوير منتجات',
                    'model_photo' => 'تصوير مع موديل',
                    'video' => 'فيديو دعائي',
                    'other' => 'أخرى',
                ];
                foreach ($types as $type) {
                    $serviceTypes[] = $typesMap[$type] ?? $type;
                }
            }
            if ($visit->service_types_other_text) {
                $serviceTypes[] = $visit->service_types_other_text;
            }

            $sheet->setCellValue('A' . $row, $visit->id);
            $sheet->setCellValue('B' . $row, $visit->client->store_name ?? '');
            $sheet->setCellValue('C' . $row, $visit->client->contact_person ?? '');
            $sheet->setCellValue('D' . $row, $visit->client->mobile ?? '');
            $sheet->setCellValue('E' . $row, $visit->client->businessType->name_ar ?? '');
            $sheet->setCellValue('F' . $row, $visit->visit_date ? date('Y-m-d', strtotime($visit->visit_date)) : '');
            $sheet->setCellValue('G' . $row, $visit->salesRep->name ?? '');
            $sheet->setCellValue('H' . $row, $statusMap[$visit->status] ?? $visit->status);
            $sheet->setCellValue('I' . $row, $visit->productCategory->name_ar ?? '');
            $sheet->setCellValue('J' . $row, $visit->estimated_product_count ?? '');
            $sheet->setCellValue('K' . $row, $visit->budget_range ?? '');
            $sheet->setCellValue('L' . $row, implode(', ', $shootingGoals));
            $sheet->setCellValue('M' . $row, implode(', ', $serviceTypes));
            $sheet->setCellValue('N' . $row, $locationMap[$visit->preferred_location] ?? '');
            $sheet->setCellValue('O' . $row, $visit->rep_notes ?? '');

            // Right-to-left alignment for Arabic
            foreach (range('A', 'O') as $col) {
                $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
            }

            $row++;
        }

        // Set RTL for the whole sheet
        $sheet->setRightToLeft(true);

        return $phpExcel;
    }
}
