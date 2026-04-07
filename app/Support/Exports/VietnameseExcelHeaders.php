<?php

namespace App\Support\Exports;

/**
 * Vietnamese column titles for Excel exports (data cells stay raw / machine-oriented).
 */
final class VietnameseExcelHeaders
{
    /**
     * @return list<string>
     */
    public static function approvedQuotationItems(): array
    {
        return [
            'Mã báo giá',
            'Tên nhà cung cấp',
            'Ngày báo giá',
            'Tên dòng (thô)',
            'Model (thô)',
            'Thương hiệu',
            'Số lượng',
            'Đơn giá',
            'VAT (%)',
            'Thành tiền dòng',
            'ID sản phẩm chuẩn',
            'Tên sản phẩm chuẩn',
            'SKU sản phẩm chuẩn',
        ];
    }

    /**
     * @return list<string>
     */
    public static function priceHistoryLines(): array
    {
        return [
            'Khóa nhóm so sánh',
            'Mã báo giá',
            'Tên nhà cung cấp',
            'Số báo giá (NCC)',
            'Ngày báo giá',
            'Tên dòng (thô)',
            'Model (thô)',
            'Thương hiệu',
            'Số lượng',
            'Đơn giá',
            'VAT (%)',
            'Tổng',
            'Thời điểm phê duyệt',
            'ID sản phẩm chuẩn',
            'Tên sản phẩm chuẩn',
            'SKU sản phẩm chuẩn',
        ];
    }

    /**
     * @return list<string>
     */
    public static function ingestionBatchesMonitoring(): array
    {
        return [
            'Mã lô',
            'Tên nhà cung cấp',
            'Thời điểm tiếp nhận',
            'Trạng thái',
            'Số tệp',
        ];
    }

    /**
     * @return list<string>
     */
    public static function lowConfidenceAiExtractions(): array
    {
        return [
            'Mã trích xuất',
            'Mã lô',
            'Tên nhà cung cấp',
            'Thời điểm tiếp nhận lô',
            'Trạng thái lô',
            'Độ tin cậy tổng (0–1)',
            'Tên mô hình AI',
        ];
    }

    /**
     * @return list<string>
     */
    public static function unmappedQuotationLines(): array
    {
        return [
            'Mã báo giá',
            'Tên nhà cung cấp',
            'Thời điểm phê duyệt',
            'Tên dòng (thô)',
            'Model (thô)',
            'Thương hiệu',
            'Số lượng',
            'Đơn giá',
            'VAT (%)',
            'Thành tiền dòng',
        ];
    }
}
