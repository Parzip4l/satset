<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BumReportsTemplateTest extends TestCase
{
    public function test_report_template_compiles_all_chart_directives(): void
    {
        $compiled = Blade::compileString(file_get_contents(resource_path('views/bum/reports.blade.php')));

        $this->assertDoesNotMatchRegularExpression('/@(php|endphp|forelse|endforelse|empty|disabled)\b/', $compiled);
        $this->assertStringContainsString("foreach(\$__currentLoopData as \$row)", $compiled);
    }
}
