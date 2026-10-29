<?php

// Deliberately synthetic targets, used only by the guarded restore verifier. Never seeded on source.
function phase04TestSla(): array
{
    $sla = \App\Services\It\SlaProfile::draft();
    $sla['calendars'][0]['mode'] = '24x7';
    $sla['calendars'][0]['key'] = 'always';
    foreach (['incident', 'service_request'] as $type) {
        $kind = $type === 'incident' ? 'restore' : 'fulfil';
        $targets = [];
        foreach (['P1' => [30, 120], 'P2' => [60, 240], 'P3' => [120, 480], 'P4' => [240, 960]] as $priority => $minutes) {
            $targets[$priority] = ['response' => $minutes[0], $kind => $minutes[1]];
        }
        $sla['policies'][] = ['key' => $type, 'name' => 'Synthetic verification '.$type, 'record_type' => $type, 'calendar_key' => 'always', 'targets' => $targets, 'pause' => ['response' => [], 'restore' => ['waiting_customer'], 'fulfil' => ['waiting_customer', 'awaiting_approval', 'needs_revision']],
            'warning_percent' => 80, 'escalations' => [['key' => 'warning', 'at_percent' => 80, 'repeat_minutes' => null, 'recipients' => ['coordinators']], ['key' => 'breach', 'at_percent' => 100, 'repeat_minutes' => 60, 'recipients' => ['coordinators']]]];
        $sla['fallback'][$type] = $type;
    }

return $sla;
}
