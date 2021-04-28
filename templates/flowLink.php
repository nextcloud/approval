<?php
$section = OCA\Approval\AppInfo\Application::ADMIN_SETTINGS_SECTION;
?>

<div id="approval_link">
    <div class="section">
        <h2>
            <?php p($l->t('Approval rules')); ?>
        </h2>
        <a href="./<?php p($section); ?>" class="external">
            <?php p($l->t('Add approval rule')); ?>
        </a>
    </div>
</div>