<?php
/**
 * Re-add Round 1 / Round 2 if a job only has system stages (e.g. after accidental delete).
 *   php scripts/heal_job_pipeline_stages.php
 */
require __DIR__ . '/../app/bootstrap.php';

$jobs = db()->fetchAll("SELECT id FROM jobs WHERE status = 'active'");
$healed = 0;
foreach ($jobs as $j) {
    $rounds = db()->fetch(
        "SELECT COUNT(*) as c FROM job_pipeline_stages WHERE job_id = ? AND is_active = 1 AND stage_type = 'round'",
        [$j['id']]
    )['c'];
    if ((int) $rounds > 0) {
        continue;
    }
    $max = (int) db()->fetch('SELECT MAX(sort_order) as m FROM job_pipeline_stages WHERE job_id = ?', [$j['id']])['m'];
    foreach (['Round 1', 'Round 2'] as $i => $name) {
        db()->insert('job_pipeline_stages', [
            'id' => uuid(),
            'job_id' => $j['id'],
            'name' => $name,
            'stage_type' => 'round',
            'sort_order' => $max + $i + 1,
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }
    db()->query(
        "UPDATE job_pipeline_stages SET is_active = 1 WHERE job_id = ? AND stage_type = 'round'",
        [$j['id']]
    );
    $healed++;
}
echo "Healed {$healed} job(s) with default Round 1 / Round 2.\n";
