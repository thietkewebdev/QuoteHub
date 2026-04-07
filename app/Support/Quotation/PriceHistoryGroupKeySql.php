<?php

namespace App\Support\Quotation;

use Illuminate\Support\Facades\DB;

/**
 * SQL expression for price-history comparison grouping (no ML).
 *
 * - If {@code mapped_product_id} is set: group key {@code p:<id>} (canonical product).
 * - Else if raw_model is non-empty after trim: group key {@code m:<lower(trim(raw_model))>}.
 * - Else: group key {@code n:<lower(trim(raw_name))>|<lower(trim(brand))>} (simple normalization only).
 */
final class PriceHistoryGroupKeySql
{
    public static function expression(string $tableAlias = 'quotation_items'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => self::mysqlExpression($tableAlias),
            'pgsql' => self::pgsqlExpression($tableAlias),
            default => self::sqliteExpression($tableAlias),
        };
    }

    /**
     * Predicate that is true when the line has a usable model string (for filters).
     */
    public static function hasNonEmptyRawModelPredicate(string $tableAlias = 'quotation_items'): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => "(NULLIF(TRIM(IFNULL(`{$tableAlias}`.`raw_model`, '')), '') IS NOT NULL)",
            'pgsql' => "(NULLIF(TRIM(COALESCE(\"{$tableAlias}\".\"raw_model\", '')), '') IS NOT NULL)",
            default => "(NULLIF(TRIM(COALESCE(\"{$tableAlias}\".\"raw_model\", '')), '') IS NOT NULL)",
        };
    }

    private static function mysqlExpression(string $t): string
    {
        $mapped = "`{$t}`.`mapped_product_id`";
        $rawModel = "TRIM(IFNULL(`{$t}`.`raw_model`, ''))";
        $rawName = "TRIM(IFNULL(`{$t}`.`raw_name`, ''))";
        $brand = "TRIM(IFNULL(`{$t}`.`brand`, ''))";

        return "CASE WHEN {$mapped} IS NOT NULL THEN CONCAT('p:', {$mapped}) WHEN {$rawModel} <> '' THEN CONCAT('m:', LOWER({$rawModel})) ELSE CONCAT('n:', LOWER({$rawName}), '|', LOWER({$brand})) END";
    }

    private static function pgsqlExpression(string $t): string
    {
        $mapped = "\"{$t}\".\"mapped_product_id\"";
        $rawModel = "TRIM(COALESCE(\"{$t}\".\"raw_model\", ''))";
        $rawName = "TRIM(COALESCE(\"{$t}\".\"raw_name\", ''))";
        $brand = "TRIM(COALESCE(\"{$t}\".\"brand\", ''))";

        return "CASE WHEN {$mapped} IS NOT NULL THEN 'p:' || CAST({$mapped} AS TEXT) WHEN {$rawModel} <> '' THEN 'm:' || LOWER({$rawModel}) ELSE 'n:' || LOWER({$rawName}) || '|' || LOWER({$brand}) END";
    }

    private static function sqliteExpression(string $t): string
    {
        $mapped = "\"{$t}\".\"mapped_product_id\"";
        $rawModel = "TRIM(COALESCE(\"{$t}\".\"raw_model\", ''))";
        $rawName = "TRIM(COALESCE(\"{$t}\".\"raw_name\", ''))";
        $brand = "TRIM(COALESCE(\"{$t}\".\"brand\", ''))";

        return "CASE WHEN {$mapped} IS NOT NULL THEN 'p:' || CAST({$mapped} AS TEXT) WHEN {$rawModel} != '' THEN 'm:' || LOWER({$rawModel}) ELSE 'n:' || LOWER({$rawName}) || '|' || LOWER({$brand}) END";
    }
}
