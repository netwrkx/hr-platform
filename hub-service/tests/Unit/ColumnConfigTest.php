<?php

namespace Tests\Unit;

use App\ServerUI\ColumnConfig;
use Tests\TestCase;

class ColumnConfigTest extends TestCase
{
    private ColumnConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new ColumnConfig();
    }

    // ── Column definitions ───────────────────────────────────────────────

    public function test_usa_returns_four_columns(): void
    {
        $columns = $this->config->getColumns('USA');

        $this->assertCount(4, $columns);
    }

    public function test_germany_returns_four_columns(): void
    {
        $columns = $this->config->getColumns('Germany');

        $this->assertCount(4, $columns);
    }

    public function test_usa_columns_include_ssn(): void
    {
        $columns = $this->config->getColumns('USA');
        $keys = array_column($columns, 'key');

        $this->assertContains('ssn', $keys);
    }

    public function test_usa_ssn_column_has_masked_flag(): void
    {
        $columns = $this->config->getColumns('USA');
        $ssn = collect($columns)->firstWhere('key', 'ssn');

        $this->assertTrue($ssn['masked']);
    }

    public function test_germany_columns_include_goal_not_ssn(): void
    {
        $columns = $this->config->getColumns('Germany');
        $keys = array_column($columns, 'key');

        $this->assertContains('goal', $keys);
        $this->assertNotContains('ssn', $keys);
    }

    public function test_usa_salary_column_has_currency_format(): void
    {
        $columns = $this->config->getColumns('USA');
        $salary = collect($columns)->firstWhere('key', 'salary');

        $this->assertEquals('currency', $salary['format']);
    }

    public function test_each_column_has_key_and_display(): void
    {
        $columns = $this->config->getColumns('USA');

        foreach ($columns as $column) {
            $this->assertArrayHasKey('key', $column);
            $this->assertArrayHasKey('display', $column);
        }
    }

    // ── SSN masking ──────────────────────────────────────────────────────

    public function test_ssn_masking_standard_format(): void
    {
        $this->assertEquals('***-**-6789', ColumnConfig::maskSsn('123-45-6789'));
    }

    public function test_ssn_masking_returns_null_for_null(): void
    {
        $this->assertNull(ColumnConfig::maskSsn(null));
    }

    public function test_ssn_masking_returns_empty_for_empty(): void
    {
        $this->assertEquals('', ColumnConfig::maskSsn(''));
    }

    public function test_unsupported_country_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->config->getColumns('France');
    }
}
