<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendAdminRenderer
{
    public function render(array $state, array $notice, array $config): void
    {
        ?>
        <div class="wrap fmb-admin-page">
            <h1><?php echo esc_html__('Frontend del medallero', 'cannes-festival-medal-tracker'); ?></h1>
            <?php $this->renderNotice($notice); ?>
            <?php $this->renderControls($state, $config); ?>
            <?php $this->renderSnapshotSummary($state); ?>
            <h2><?php echo esc_html__('Medallero publicado actual', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderMedalTable($state['published_rows'] ?? [], __('Todavia no hay datos publicados en el frontend.', 'cannes-festival-medal-tracker')); ?>
            <h2><?php echo esc_html__('Cambios pendientes para publicar', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderDeltaTable($state['pending_changes'] ?? []); ?>
            <h2><?php echo esc_html__('Asi quedaria despues de publicar', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderMedalTable($state['live_rows'] ?? [], __('El medallero interno esta vacio.', 'cannes-festival-medal-tracker')); ?>
        </div>
        <?php
    }

    private function renderNotice(array $notice): void
    {
        if (empty($notice)) {
            return;
        }

        $class = !empty($notice['error'])
            ? 'notice notice-error is-dismissible'
            : 'notice notice-success is-dismissible';
        $message = !empty($notice['error']) ? (string) $notice['error'] : (string) ($notice['message'] ?? '');

        if ('' === $message) {
            return;
        }

        ?>
        <div class="<?php echo esc_attr($class); ?>">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }

    private function renderControls(array $state, array $config): void
    {
        ?>
        <div class="fmb-frontend-controls">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['visibility_action']); ?>">
                <?php wp_nonce_field((string) $config['visibility_nonce_action'], (string) $config['visibility_nonce_field']); ?>
                <label class="fmb-toggle-row" for="fmb_frontend_enabled">
                    <input
                        type="checkbox"
                        id="fmb_frontend_enabled"
                        name="fmb_frontend_enabled"
                        value="1"
                        <?php checked(!empty($state['enabled'])); ?>
                    >
                    <span><?php echo esc_html__('Mostrar shortcodes en el frontend', 'cannes-festival-medal-tracker'); ?></span>
                </label>
                <?php submit_button(__('Guardar visibilidad', 'cannes-festival-medal-tracker'), 'secondary', 'submit', false); ?>
            </form>

            <form
                method="post"
                action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                data-fmb-confirm="<?php echo esc_attr__('Publicar ahora el medallero interno? Los shortcodes empezaran a mostrar este nuevo lote publicado.', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-title="<?php echo esc_attr__('Publicar datos del frontend', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-button="<?php echo esc_attr__('Publicar datos', 'cannes-festival-medal-tracker'); ?>"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['publish_action']); ?>">
                <?php wp_nonce_field((string) $config['publish_nonce_action'], (string) $config['publish_nonce_field']); ?>
                <?php submit_button(__('Publicar datos', 'cannes-festival-medal-tracker'), 'primary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    private function renderSnapshotSummary(array $state): void
    {
        ?>
        <div class="fmb-frontend-summary">
            <div>
                <strong><?php echo esc_html__('Estado de shortcodes', 'cannes-festival-medal-tracker'); ?></strong>
                <span><?php echo !empty($state['enabled']) ? esc_html__('Visibles', 'cannes-festival-medal-tracker') : esc_html__('Ocultos', 'cannes-festival-medal-tracker'); ?></span>
            </div>
            <div>
                <strong><?php echo esc_html__('Ultima publicacion', 'cannes-festival-medal-tracker'); ?></strong>
                <span>
                    <?php
                    echo esc_html(
                        '' !== (string) ($state['published_at'] ?? '')
                            ? (string) $state['published_at']
                            : __('Nunca', 'cannes-festival-medal-tracker')
                    );
                    ?>
                </span>
            </div>
            <div>
                <strong><?php echo esc_html__('Cambios pendientes', 'cannes-festival-medal-tracker'); ?></strong>
                <span><?php echo esc_html((string) count($state['pending_changes'] ?? [])); ?></span>
            </div>
        </div>
        <?php
    }

    private function renderMedalTable(array $rows, string $emptyMessage): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html($emptyMessage) . '</p>';
            return;
        }

        ?>
        <table class="widefat striped fmb-admin-standings">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['country'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) absint($row['gp'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['gold'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['silver'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['bronze'] ?? 0)); ?></td>
                        <td><?php echo esc_html((string) absint($row['total'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function renderDeltaTable(array $rows): void
    {
        if (empty($rows)) {
            echo '<p>' . esc_html__('No hay diferencias pendientes entre lo publicado y el medallero interno.', 'cannes-festival-medal-tracker') . '</p>';
            return;
        }

        ?>
        <table class="widefat striped fmb-admin-standings fmb-delta-table">
            <thead>
                <tr>
                    <th scope="col"><?php echo esc_html__('Pais', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('GP', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Oro', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Plata', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Bronce', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['country'] ?? '')); ?></td>
                        <td><?php echo esc_html($this->signedNumber((int) ($row['gp'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->signedNumber((int) ($row['gold'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->signedNumber((int) ($row['silver'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->signedNumber((int) ($row['bronze'] ?? 0))); ?></td>
                        <td><?php echo esc_html($this->signedNumber((int) ($row['total'] ?? 0))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function signedNumber(int $value): string
    {
        if ($value > 0) {
            return '+' . $value;
        }

        return (string) $value;
    }
}
