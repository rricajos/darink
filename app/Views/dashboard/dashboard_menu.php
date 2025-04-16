<ul class="dashboard-menu grid-auto-fit-256px prevent-wrap">
    <?php foreach ($items as $item): ?>
        <?= view('dashboard/dashboard_menu_item', [
            'url'    => $item['url'],
            'icon'   => $item['icon'],
            'label'  => $item['label'],
            'active' => $item['active'] ?? false
        ]) ?>
    <?php endforeach; ?>
</ul>
