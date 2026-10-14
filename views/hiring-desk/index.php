<?php $breadcrumbs = nav_trail_dashboard('Hiring desk (preview)'); ?>

<div class="hiring-desk-beta">
    <?php include BASE_PATH . '/views/hiring-desk/_beta_banner.php'; ?>

    <div class="page-header" style="margin-top:16px">
        <div>
            <h1>Hiring desk</h1>
            <p class="text-muted" style="margin:4px 0 0;font-size:0.9rem">Choose a job role to review applicants, scores, and side-by-side comparison.</p>
        </div>
    </div>

    <div class="table-wrap">
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Job role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Applicants</th>
                        <th>AI reports</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr><td colspan="6" class="empty-state">No job roles available.</td></tr>
                    <?php else: foreach ($jobs as $j): ?>
                    <tr>
                        <td><?= e($j['title']) ?></td>
                        <td><?= e($j['department_name'] ?? '—') ?></td>
                        <td><span class="badge badge-<?= e($j['status']) ?>"><?= e(ucfirst($j['status'])) ?></span></td>
                        <td><?= (int) ($j['applicant_count'] ?? 0) ?></td>
                        <td><?= (int) ($j['report_count'] ?? 0) ?></td>
                        <td>
                            <a href="<?= url('hiring-desk/jobs/' . $j['id']) ?>" class="btn btn-primary btn-sm">Open desk</a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
