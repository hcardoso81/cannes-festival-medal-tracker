<?php

declare(strict_types=1);

namespace FestivalMedalTracker\UI\Admin;

if (!defined('ABSPATH')) {
    exit;
}

final class FrontendAdminRenderer
{
    private AdminTabsRenderer $tabs;

    private AdminShortcodePreviewRenderer $shortcodes;

    public function __construct()
    {
        $this->tabs = new AdminTabsRenderer();
        $this->shortcodes = new AdminShortcodePreviewRenderer();
    }

    public function render(array $state, array $notice, array $config): void
    {
        ?>
        <div class="wrap fmb-admin-page">
            <h1><?php echo esc_html__('Frontend del medallero', 'cannes-festival-medal-tracker'); ?></h1>
            <?php $this->tabs->render('frontend'); ?>
            <?php $this->renderNotice($notice); ?>
            <?php $this->renderControls($state, $config); ?>
            <?php $this->renderSnapshotSummary($state); ?>
            <h2><?php echo esc_html__('Medallero publicado actual', 'cannes-festival-medal-tracker'); ?></h2>
            <?php $this->renderMedalTable($state['published_rows'] ?? [], __('Todavia no hay datos publicados en el frontend.', 'cannes-festival-medal-tracker')); ?>
            <?php $this->renderPublicationPreview($state); ?>
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
                data-fmb-confirm="<?php echo esc_attr__('Publicar ahora los datos pendientes? Los shortcodes empezaran a mostrar este nuevo lote publicado.', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-title="<?php echo esc_attr__('Publicar datos pendientes', 'cannes-festival-medal-tracker'); ?>"
                data-fmb-confirm-button="<?php echo esc_attr__('Publicar datos pendientes', 'cannes-festival-medal-tracker'); ?>"
            >
                <input type="hidden" name="action" value="<?php echo esc_attr((string) $config['publish_action']); ?>">
                <?php wp_nonce_field((string) $config['publish_nonce_action'], (string) $config['publish_nonce_field']); ?>
                <?php submit_button(__('Publicar datos pendientes', 'cannes-festival-medal-tracker'), 'primary', 'submit', false); ?>
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
                <strong><?php echo esc_html__('Archivos a publicar', 'cannes-festival-medal-tracker'); ?></strong>
                <?php $this->renderPendingFiles($state['pending_files'] ?? []); ?>
            </div>
        </div>
        <?php
    }

    private function renderPendingFiles(array $files): void
    {
        $files = array_values(array_filter(array_map('strval', $files)));

        if (empty($files)) {
            ?>
            <span><?php echo esc_html__('Sin archivos pendientes', 'cannes-festival-medal-tracker'); ?></span>
            <?php
            return;
        }

        ?>
        <ul class="fmb-pending-file-list">
            <?php foreach ($files as $file) : ?>
                <li><?php echo esc_html($file); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }

    private function renderPublicationPreview(array $state): void
    {
        $changes = is_array($state['pending_changes'] ?? null) ? $state['pending_changes'] : [];
        $liveRows = is_array($state['live_rows'] ?? null) ? $state['live_rows'] : [];
        $hasChanges = !empty($changes);
        $previewRows = $hasChanges ? $liveRows : [];
        ?>
        <details class="fmb-frontend-publication-preview">
            <summary>
                <?php echo esc_html__('Vista previa de publicacion del frontend', 'cannes-festival-medal-tracker'); ?>
            </summary>
            <div class="fmb-frontend-publication-preview__body">
                <div class="<?php echo esc_attr($hasChanges ? 'fmb-publication-change-box fmb-publication-change-box--has-changes' : 'fmb-publication-change-box'); ?>">
                    <strong>
                        <?php
                        echo esc_html(
                            $hasChanges
                                ? __('Hay cambios pendientes para publicar', 'cannes-festival-medal-tracker')
                                : __('No hay cambios pendientes para publicar', 'cannes-festival-medal-tracker')
                        );
                        ?>
                    </strong>
                    <span>
                        <?php
                        echo esc_html(
                            $hasChanges
                                ? sprintf(
                                    /* translators: %d: countries with pending publication changes. */
                                    __('La publicacion actualizaria %d paises del medallero visible en el frontend.', 'cannes-festival-medal-tracker'),
                                    count($changes)
                                )
                                : __('El medallero publicado ya coincide con el medallero interno.', 'cannes-festival-medal-tracker')
                        );
                        ?>
                    </span>
                </div>
                <details class="fmb-accordion">
                    <summary>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: countries with pending publication changes. */
                                __('Cambios pendientes para publicar: %d paises. Ver detalle.', 'cannes-festival-medal-tracker'),
                                count($changes)
                            )
                        );
                        ?>
                    </summary>
                    <?php $this->renderDeltaTable($changes); ?>
                </details>
                <details class="fmb-accordion">
                    <summary>
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %d: countries in the medal table after publishing. */
                                __('Asi quedaria despues de publicar: %d paises. Ver detalle.', 'cannes-festival-medal-tracker'),
                                count($previewRows)
                            )
                        );
                        ?>
                    </summary>
                    <?php $this->renderMedalTable($previewRows, __('No hay cambios pendientes para previsualizar despues de publicar.', 'cannes-festival-medal-tracker')); ?>
                </details>
                <div class="fmb-preview-shortcodes">
                    <h3><?php echo esc_html__('Shortcodes despues de publicar', 'cannes-festival-medal-tracker'); ?></h3>
                    <?php
                    if ($hasChanges) {
                        $this->shortcodes->render($liveRows);
                    } else {
                        echo '<p>' . esc_html__('No hay cambios pendientes para previsualizar en los shortcodes.', 'cannes-festival-medal-tracker') . '</p>';
                    }
                    ?>
                </div>
            </div>
        </details>
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
                    <th scope="col"><?php echo esc_html__('Titanio', 'cannes-festival-medal-tracker'); ?></th>
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
                        <td><?php echo esc_html((string) absint($row['titanium'] ?? 0)); ?></td>
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
                    <th scope="col"><?php echo esc_html__('Titanio', 'cannes-festival-medal-tracker'); ?></th>
                    <th scope="col"><?php echo esc_html__('Total', 'cannes-festival-medal-tracker'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['country'] ?? '')); ?></td>
                        <?php $this->renderDeltaCell((int) ($row['gp'] ?? 0)); ?>
                        <?php $this->renderDeltaCell((int) ($row['gold'] ?? 0)); ?>
                        <?php $this->renderDeltaCell((int) ($row['silver'] ?? 0)); ?>
                        <?php $this->renderDeltaCell((int) ($row['bronze'] ?? 0)); ?>
                        <?php $this->renderDeltaCell((int) ($row['titanium'] ?? 0)); ?>
                        <?php $this->renderDeltaCell((int) ($row['total'] ?? 0)); ?>
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

    private function renderDeltaCell(int $value): void
    {
        $class = $value > 0 ? 'fmb-delta-positive' : '';
        ?>
        <td class="<?php echo esc_attr($class); ?>"><?php echo esc_html($this->signedNumber($value)); ?></td>
        <?php
    }
}
